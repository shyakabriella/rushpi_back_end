<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\V1\Customer\StoreProductOrderRequest;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\ProductVariant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ProductOrderController extends Controller
{
    private const DELIVERY_FEES = [
        'standard' => 2000,
        'express' => 5000,
        'pickup' => 0,
    ];

    public function store(
        StoreProductOrderRequest $request
    ): JsonResponse {
        $data = $request->validated();

        $order = DB::transaction(
            function () use ($request, $data): ProductOrder {
                $resolvedItems = [];
                $subtotal = 0.0;
                $currency = 'RWF';

                foreach ($data['items'] as $index => $item) {
                    $product = Product::query()
                        ->where(
                            'public_id',
                            $item['product_public_id']
                        )
                        ->firstOrFail();

                    if (! $product->isPubliclyVisible()) {
                        throw ValidationException::withMessages([
                            "items.$index.product_public_id" =>
                                'This product is not available.',
                        ]);
                    }

                    $variantQuery = ProductVariant::query()
                        ->where('product_id', $product->id)
                        ->where('is_active', true);

                    if (
                        ! empty($item['variant_public_id'])
                    ) {
                        $variantQuery->where(
                            'public_id',
                            $item['variant_public_id']
                        );
                    } else {
                        $variantQuery->where(
                            'is_default',
                            true
                        );
                    }

                    $variant = $variantQuery
                        ->lockForUpdate()
                        ->first();

                    if ($variant === null) {
                        throw ValidationException::withMessages([
                            "items.$index.variant_public_id" =>
                                'A valid product variant was not found.',
                        ]);
                    }

                    $variant->load([
                        'price',
                        'product',
                    ]);

                    if ($variant->price === null) {
                        throw ValidationException::withMessages([
                            "items.$index.product_public_id" =>
                                'This product does not have a price.',
                        ]);
                    }

                    $stock = InventoryStock::query()
                        ->where(
                            'product_variant_id',
                            $variant->id
                        )
                        ->lockForUpdate()
                        ->first();

                    $quantity = (int) $item['quantity'];

                    if (
                        $stock === null
                        || ! $stock->canReserve($quantity)
                    ) {
                        throw ValidationException::withMessages([
                            "items.$index.quantity" =>
                                "Insufficient stock for {$product->name}.",
                        ]);
                    }

                    $itemCurrency = strtoupper(
                        $variant->price->currency
                    );

                    if (
                        $resolvedItems !== []
                        && $currency !== $itemCurrency
                    ) {
                        throw ValidationException::withMessages([
                            'items' =>
                                'All products must use the same currency.',
                        ]);
                    }

                    $currency = $itemCurrency;
                    $unitPrice = (float)
                        $variant->price->selling_price;

                    $lineTotal = round(
                        $unitPrice * $quantity,
                        2
                    );

                    $subtotal += $lineTotal;

                    $resolvedItems[] = [
                        'product' => $product,
                        'variant' => $variant,
                        'stock' => $stock,
                        'quantity' => $quantity,
                        'unit_price' => $unitPrice,
                        'line_total' => $lineTotal,
                    ];
                }

                $deliveryFee = (float)
                    self::DELIVERY_FEES[
                        $data['delivery_method']
                    ];

                $total = round(
                    $subtotal + $deliveryFee,
                    2
                );

                $order = ProductOrder::query()->create([
                    'user_id' => $request->user()?->id,
                    'status' =>
                        ProductOrder::STATUS_PENDING,
                    'payment_status' =>
                        ProductOrder::PAYMENT_PENDING,
                    'payment_method' =>
                        $data['payment_method'],
                    'currency' => $currency,
                    'subtotal' => $subtotal,
                    'delivery_fee' => $deliveryFee,
                    'total' => $total,
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'email' => $data['email'],
                    'phone' => $data['phone'],
                    'delivery_method' =>
                        $data['delivery_method'],
                    'delivery_province' =>
                        $data['delivery_province'] ?? '',
                    'delivery_district' =>
                        $data['delivery_district'] ?? '',
                    'delivery_sector' =>
                        $data['delivery_sector'] ?? '',
                    'delivery_street' =>
                        $data['delivery_street'] ?? '',
                    'delivery_instructions' =>
                        $data['delivery_instructions']
                            ?? null,
                    'placed_at' => now(),
                ]);

                foreach ($resolvedItems as $resolved) {
                    $product = $resolved['product'];
                    $variant = $resolved['variant'];
                    $stock = $resolved['stock'];

                    $order->items()->create([
                        'seller_profile_id' =>
                            $product->seller_profile_id,
                        'product_id' => $product->id,
                        'product_variant_id' =>
                            $variant->id,
                        'product_name' =>
                            $product->name,
                        'variant_name' =>
                            $variant->name,
                        'sku' => $variant->sku,
                        'currency' => $currency,
                        'unit_price' =>
                            $resolved['unit_price'],
                        'quantity' =>
                            $resolved['quantity'],
                        'line_total' =>
                            $resolved['line_total'],
                        'fulfilment_status' =>
                            ProductOrder::STATUS_PENDING,
                    ]);

                    $stock->increment(
                        'quantity_reserved',
                        $resolved['quantity']
                    );
                }

                return $order->load([
                    'items.variant',
                ]);
            },
            3
        );

        return response()->json([
            'success' => true,
            'message' =>
                'Product order created successfully.',
            'data' => [
                'public_id' => $order->public_id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'payment_status' =>
                    $order->payment_status,
                'currency' => $order->currency,
                'subtotal' => $order->subtotal,
                'delivery_fee' =>
                    $order->delivery_fee,
                'total' => $order->total,
                'placed_at' =>
                    $order->placed_at?->toISOString(),
                'items' => $order->items->map(
                    static fn ($item): array => [
                        'product_name' =>
                            $item->product_name,
                        'variant_name' =>
                            $item->variant_name,
                        'sku' => $item->sku,
                        'unit_price' =>
                            $item->unit_price,
                        'quantity' =>
                            $item->quantity,
                        'line_total' =>
                            $item->line_total,
                    ]
                ),
            ],
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $orders = ProductOrder::query()
            ->with('items')
            ->where('user_id', $request->user()->id)
            ->latest('id')
            ->paginate(
                min(
                    max(
                        (int) $request->input('per_page', 20),
                        1
                    ),
                    100
                )
            );

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    public function show(
        Request $request,
        ProductOrder $productOrder
    ): JsonResponse {
        abort_unless(
            $productOrder->user_id === $request->user()->id,
            404
        );

        $productOrder->load([
            'items.variant',
        ]);

        return response()->json([
            'success' => true,
            'data' => $productOrder,
        ]);
    }

    public function cancel(
        Request $request,
        ProductOrder $productOrder
    ): JsonResponse {
        abort_unless(
            $productOrder->user_id === $request->user()->id,
            404
        );

        $data = $request->validate([
            'reason' => [
                'nullable',
                'string',
                'max:1000',
            ],
        ]);

        if (! $productOrder->canBeCancelled()) {
            return response()->json([
                'success' => false,
                'message' =>
                    'This order can no longer be cancelled.',
            ], 409);
        }

        DB::transaction(function () use (
            $productOrder,
            $data
        ): void {
            $order = ProductOrder::query()
                ->whereKey($productOrder->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $order->canBeCancelled()) {
                throw ValidationException::withMessages([
                    'order' =>
                        'This order can no longer be cancelled.',
                ]);
            }

            $order->load('items');

            foreach ($order->items as $item) {
                $stock = InventoryStock::query()
                    ->where(
                        'product_variant_id',
                        $item->product_variant_id
                    )
                    ->lockForUpdate()
                    ->first();

                if ($stock !== null) {
                    $stock->quantity_reserved = max(
                        0,
                        $stock->quantity_reserved
                            - $item->quantity
                    );

                    $stock->save();
                }

                $item->update([
                    'fulfilment_status' =>
                        ProductOrder::STATUS_CANCELLED,
                    'cancelled_at' => now(),
                ]);
            }

            $order->update([
                'status' =>
                    ProductOrder::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancellation_reason' =>
                    $data['reason'] ?? null,
            ]);
        }, 3);

        return response()->json([
            'success' => true,
            'message' =>
                'Order cancelled successfully.',
            'data' => $productOrder
                ->fresh()
                ->load('items'),
        ]);
    }


}
