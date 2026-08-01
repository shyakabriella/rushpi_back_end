<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\InventoryStock
 */
class SellerInventoryResource extends JsonResource
{
    /**
     * Transform inventory stock into an API response.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            /*
             * Internal inventory database IDs are intentionally
             * not exposed through the API.
             */
            'quantity_on_hand' =>
                (int) $this->quantity_on_hand,

            'quantity_reserved' =>
                (int) $this->quantity_reserved,

            'available_quantity' =>
                $this->availableQuantity(),

            'reorder_level' =>
                (int) $this->reorder_level,

            'allow_backorder' =>
                (bool) $this->allow_backorder,

            /*
             * Human-readable stock condition.
             *
             * Expected values may include:
             * - in_stock
             * - low_stock
             * - out_of_stock
             * - backorder
             */
            'stock_status' =>
                $this->stockStatus(),

            'is_in_stock' =>
                $this->isInStock(),

            'is_low_stock' =>
                $this->isLowStock(),

            'is_out_of_stock' =>
                $this->isOutOfStock(),

            /*
             * Useful stock summary for seller dashboards.
             */
            'summary' => [
                'physical_stock' =>
                    (int) $this->quantity_on_hand,

                'reserved_stock' =>
                    (int) $this->quantity_reserved,

                'sellable_stock' =>
                    $this->availableQuantity(),

                'needs_restocking' =>
                    $this->isLowStock()
                    || $this->isOutOfStock(),

                'accepting_backorders' =>
                    (bool) $this->allow_backorder,
            ],

            /*
             * Variant information is returned only when the
             * controller loads the variant relationship.
             */
            'variant' => $this->whenLoaded(
                'variant',
                function (): ?array {
                    if ($this->variant === null) {
                        return null;
                    }

                    return [
                        'public_id' =>
                            $this->variant->public_id,

                        'sku' =>
                            $this->variant->sku,

                        'barcode' =>
                            $this->variant->barcode,

                        'name' =>
                            $this->variant->name,

                        'attributes' =>
                            $this->variant->attributes ?? [],

                        'is_default' =>
                            (bool) $this->variant->is_default,

                        'is_active' =>
                            (bool) $this->variant->is_active,
                    ];
                }
            ),

            /*
             * Product information can be returned when the
             * nested variant.product relationship is loaded.
             */
            'product' => $this->when(
                $this->relationLoaded('variant')
                && $this->variant !== null
                && $this->variant->relationLoaded('product'),
                function (): ?array {
                    $product = $this->variant?->product;

                    if ($product === null) {
                        return null;
                    }

                    return [
                        'public_id' =>
                            $product->public_id,

                        'name' =>
                            $product->name,

                        'slug' =>
                            $product->slug,

                        'status' =>
                            $product->status?->value
                            ?? $product->status,
                    ];
                }
            ),

            /*
             * Number of immutable stock movement records.
             * Returned only when the controller loads the count.
             */
            'stock_movements_count' =>
                $this->when(
                    isset($this->stock_movements_count),
                    (int) ($this->stock_movements_count ?? 0)
                ),

            'created_at' =>
                $this->created_at?->toISOString(),

            'updated_at' =>
                $this->updated_at?->toISOString(),
        ];
    }
}
