<?php

declare(strict_types=1);

namespace App\Services\Orders;

use App\Models\InventoryStock;
use App\Models\ProductOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ProductOrderStatusService
{
    private const TRANSITIONS = [
        'pending' => [
            'confirmed',
            'cancelled',
        ],
        'confirmed' => [
            'processing',
            'cancelled',
        ],
        'processing' => [
            'shipped',
            'cancelled',
        ],
        'shipped' => [
            'delivered',
        ],
        'delivered' => [],
        'cancelled' => [],
    ];

    public function update(
        ProductOrder $productOrder,
        string $newStatus,
        ?string $reason = null
    ): ProductOrder {
        return DB::transaction(
            function () use (
                $productOrder,
                $newStatus,
                $reason
            ): ProductOrder {
                $order = ProductOrder::query()
                    ->whereKey($productOrder->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $allowed = self::TRANSITIONS[
                    $order->status
                ] ?? [];

                if (! in_array(
                    $newStatus,
                    $allowed,
                    true
                )) {
                    throw ValidationException::withMessages([
                        'status' => sprintf(
                            'Order cannot move from %s to %s.',
                            $order->status,
                            $newStatus
                        ),
                    ]);
                }

                $order->load('items');

                if ($newStatus === 'cancelled') {
                    $this->releaseStock($order);
                }

                if ($newStatus === 'delivered') {
                    $this->completeStock($order);
                }

                $itemChanges = [
                    'fulfilment_status' => $newStatus,
                ];

                if ($newStatus === 'confirmed') {
                    $itemChanges['confirmed_at'] = now();
                }

                if ($newStatus === 'shipped') {
                    $itemChanges['shipped_at'] = now();
                }

                if ($newStatus === 'delivered') {
                    $itemChanges['delivered_at'] = now();
                }

                if ($newStatus === 'cancelled') {
                    $itemChanges['cancelled_at'] = now();
                }

                $order->items()->update($itemChanges);

                $orderChanges = [
                    'status' => $newStatus,
                ];

                if ($newStatus === 'confirmed') {
                    $orderChanges['confirmed_at'] = now();
                }

                if ($newStatus === 'cancelled') {
                    $orderChanges['cancelled_at'] = now();
                    $orderChanges['cancellation_reason'] =
                        $reason;
                }

                if (
                    $newStatus === 'delivered'
                    && $order->payment_method === 'cash'
                ) {
                    $orderChanges['payment_status'] =
                        ProductOrder::PAYMENT_PAID;
                }

                $order->update($orderChanges);

                return $order->fresh()->load('items');
            },
            3
        );
    }

    private function releaseStock(
        ProductOrder $order
    ): void {
        foreach ($order->items as $item) {
            $stock = $this->lockedStock(
                $item->product_variant_id
            );

            if ($stock === null) {
                continue;
            }

            $stock->quantity_reserved = max(
                0,
                $stock->quantity_reserved
                    - $item->quantity
            );

            $stock->save();
        }
    }

    private function completeStock(
        ProductOrder $order
    ): void {
        foreach ($order->items as $item) {
            $stock = $this->lockedStock(
                $item->product_variant_id
            );

            if ($stock === null) {
                continue;
            }

            $stock->quantity_reserved = max(
                0,
                $stock->quantity_reserved
                    - $item->quantity
            );

            $stock->quantity_on_hand = max(
                0,
                $stock->quantity_on_hand
                    - $item->quantity
            );

            $stock->save();
        }
    }

    private function lockedStock(
        int $variantId
    ): ?InventoryStock {
        return InventoryStock::query()
            ->where(
                'product_variant_id',
                $variantId
            )
            ->lockForUpdate()
            ->first();
    }
}
