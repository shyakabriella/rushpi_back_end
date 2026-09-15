<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\API\V1\Admin\UpdateProductOrderStatusRequest;
use App\Services\Orders\ProductOrderStatusService;
use App\Models\ProductOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class ProductOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => [
                'nullable',
                Rule::in([
                    'pending',
                    'confirmed',
                    'processing',
                    'shipped',
                    'delivered',
                    'cancelled',
                ]),
            ],
            'q' => ['nullable', 'string', 'max:150'],
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ]);

        $search = trim((string) ($data['q'] ?? ''));

        $orders = ProductOrder::query()
            ->with(['items', 'customer:id,name,email'])
            ->when(
                $data['status'] ?? null,
                fn ($query, $status) =>
                    $query->where('status', $status)
            )
            ->when(
                $search !== '',
                fn ($query) => $query->where(
                    function ($searchQuery) use ($search): void {
                        $searchQuery
                            ->where(
                                'order_number',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'email',
                                'like',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'phone',
                                'like',
                                "%{$search}%"
                            );
                    }
                )
            )
            ->latest('id')
            ->paginate($data['per_page'] ?? 20);

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    public function show(
        ProductOrder $productOrder
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => $productOrder->load([
                'items.sellerProfile',
                'items.variant',
                'customer:id,name,email',
            ]),
        ]);
    }

    public function updateStatus(
        UpdateProductOrderStatusRequest $request,
        ProductOrder $productOrder,
        ProductOrderStatusService $service
    ): JsonResponse {
        $data = $request->validated();

        $order = $service->update(
            $productOrder,
            $data['status'],
            $data['reason'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' =>
                'Order status updated successfully.',
            'data' => $order,
        ]);
    }


}
