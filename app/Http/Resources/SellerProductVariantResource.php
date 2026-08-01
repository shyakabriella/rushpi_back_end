<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\ProductVariant
 */
class SellerProductVariantResource extends JsonResource
{
    /**
     * Transform the product variant into an API response.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,

            'sku' => $this->sku,

            'barcode' => $this->barcode,

            'name' => $this->name,

            'attributes' => $this->attributes ?? [],

            'dimensions' => [
                'weight_grams' => $this->weight_grams !== null
                    ? (int) $this->weight_grams
                    : null,

                'length_cm' => $this->length_cm,

                'width_cm' => $this->width_cm,

                'height_cm' => $this->height_cm,
            ],

            'is_default' => (bool) $this->is_default,

            'is_active' => (bool) $this->is_active,

            'sort_order' => (int) $this->sort_order,

            /*
             * Price is returned only when the controller
             * loads the price relationship.
             *
             * Cost price is included here because this is a
             * seller-only resource. It must not appear in the
             * future public product resource.
             */
            'price' => $this->whenLoaded(
                'price',
                function (): ?array {
                    if ($this->price === null) {
                        return null;
                    }

                    return [
                        'currency' => $this->price->currency,

                        'selling_price' =>
                            $this->price->selling_price,

                        'compare_at_price' =>
                            $this->price->compare_at_price,

                        'cost_price' =>
                            $this->price->cost_price,

                        'is_discounted' =>
                            $this->price->isDiscounted(),

                        'discount_percentage' =>
                            $this->price->discountPercentage(),

                        'formatted_selling_price' =>
                            $this->price
                                ->formattedSellingPrice(),
                    ];
                }
            ),

            /*
             * Inventory is returned only when the controller
             * loads the inventoryStock relationship.
             */
            'inventory' => $this->whenLoaded(
                'inventoryStock',
                function (): ?array {
                    if ($this->inventoryStock === null) {
                        return null;
                    }

                    return [
                        'quantity_on_hand' =>
                            (int) $this->inventoryStock
                                ->quantity_on_hand,

                        'quantity_reserved' =>
                            (int) $this->inventoryStock
                                ->quantity_reserved,

                        'available_quantity' =>
                            $this->inventoryStock
                                ->availableQuantity(),

                        'reorder_level' =>
                            (int) $this->inventoryStock
                                ->reorder_level,

                        'allow_backorder' =>
                            (bool) $this->inventoryStock
                                ->allow_backorder,

                        'stock_status' =>
                            $this->inventoryStock
                                ->stockStatus(),

                        'is_in_stock' =>
                            $this->inventoryStock
                                ->isInStock(),

                        'is_low_stock' =>
                            $this->inventoryStock
                                ->isLowStock(),

                        'is_out_of_stock' =>
                            $this->inventoryStock
                                ->isOutOfStock(),
                    ];
                }
            ),

            /*
             * Variant media is returned only when loaded.
             */
            'media' => $this->whenLoaded(
                'media',
                function () {
                    return $this->media->map(
                        function ($media): array {
                            return [
                                'public_id' =>
                                    $media->public_id,

                                'media_type' =>
                                    $media->media_type?->value
                                    ?? $media->media_type,

                                'original_name' =>
                                    $media->original_name,

                                'mime_type' =>
                                    $media->mime_type,

                                'size_bytes' =>
                                    (int) $media->size_bytes,

                                'alt_text' =>
                                    $media->alt_text,

                                'sort_order' =>
                                    (int) $media->sort_order,

                                'is_primary' =>
                                    (bool) $media->is_primary,

                                'url' =>
                                    $media->url(),
                            ];
                        }
                    )->values();
                }
            ),

            'stock_movements_count' =>
                $this->whenCounted('stockMovements'),

            'media_count' =>
                $this->whenCounted('media'),

            /*
             * These values are calculated only when the
             * required relationships are already loaded.
             */
            'is_sellable' => $this->when(
                $this->relationLoaded('price')
                && $this->relationLoaded('product'),
                fn (): bool => $this->isSellable()
            ),

            'created_at' =>
                $this->created_at?->toISOString(),

            'updated_at' =>
                $this->updated_at?->toISOString(),
        ];
    }
}
