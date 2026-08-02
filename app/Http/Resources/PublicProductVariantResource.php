<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicProductVariantResource extends JsonResource
{
    /**
     * Transform the product variant into public catalog data.
     *
     * This resource must never expose:
     * - cost price
     * - reserved stock
     * - internal database identifiers
     * - inventory movement history
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'public_id' => (string) $this->public_id,

            'sku' => (string) $this->sku,

            'name' => (string) $this->name,

            'attributes' => is_array($this->attributes)
                ? $this->attributes
                : [],

            'dimensions' => [
                'weight_grams' => $this->weight_grams !== null
                    ? (int) $this->weight_grams
                    : null,

                'length_cm' => $this->length_cm !== null
                    ? (float) $this->length_cm
                    : null,

                'width_cm' => $this->width_cm !== null
                    ? (float) $this->width_cm
                    : null,

                'height_cm' => $this->height_cm !== null
                    ? (float) $this->height_cm
                    : null,
            ],

            'is_default' => (bool) $this->is_default,

            /*
            |--------------------------------------------------------------------------
            | Public selling price
            |--------------------------------------------------------------------------
            |
            | Cost price and profit information are intentionally excluded.
            |
            */

            'price' => $this->when(
                $this->relationLoaded('price') && $this->price !== null,
                fn (): array => $this->publicPrice()
            ),

            /*
            |--------------------------------------------------------------------------
            | Public stock availability
            |--------------------------------------------------------------------------
            |
            | Customers can see whether the item is available, but they must
            | not receive reserved stock or internal stock-audit information.
            |
            */

            'inventory' => $this->when(
                $this->relationLoaded('inventoryStock')
                    && $this->inventoryStock !== null,
                fn (): array => $this->publicInventory()
            ),

            /*
            |--------------------------------------------------------------------------
            | Variant media
            |--------------------------------------------------------------------------
            */

            'media' => $this->when(
                $this->relationLoaded('media'),
                fn (): array => $this->publicMedia()
            ),
        ];
    }

    /**
     * Return customer-safe pricing information.
     *
     * @return array<string, mixed>
     */
    private function publicPrice(): array
    {
        $currency = strtoupper(
            (string) ($this->price->currency ?? 'RWF')
        );

        $sellingPrice = (float) $this->price->selling_price;

        $compareAtPrice = $this->price->compare_at_price !== null
            ? (float) $this->price->compare_at_price
            : null;

        $isDiscounted = $compareAtPrice !== null
            && $compareAtPrice > $sellingPrice;

        $discountAmount = $isDiscounted
            ? $compareAtPrice - $sellingPrice
            : null;

        $discountPercentage = $isDiscounted
            && $compareAtPrice > 0
                ? round(
                    ($discountAmount / $compareAtPrice) * 100,
                    2
                )
                : null;

        return [
            'currency' => $currency,

            'selling_price' => number_format(
                $sellingPrice,
                2,
                '.',
                ''
            ),

            'compare_at_price' => $compareAtPrice !== null
                ? number_format(
                    $compareAtPrice,
                    2,
                    '.',
                    ''
                )
                : null,

            'formatted_selling_price' => sprintf(
                '%s %s',
                $currency,
                number_format($sellingPrice, 2)
            ),

            'formatted_compare_at_price' => $compareAtPrice !== null
                ? sprintf(
                    '%s %s',
                    $currency,
                    number_format($compareAtPrice, 2)
                )
                : null,

            'discount' => [
                'is_discounted' => $isDiscounted,

                'amount' => $discountAmount !== null
                    ? number_format(
                        $discountAmount,
                        2,
                        '.',
                        ''
                    )
                    : null,

                'percentage' => $discountPercentage,
            ],
        ];
    }

    /**
     * Return customer-safe inventory information.
     *
     * @return array<string, mixed>
     */
    private function publicInventory(): array
    {
        $quantityOnHand = (int) (
            $this->inventoryStock->quantity_on_hand ?? 0
        );

        $quantityReserved = (int) (
            $this->inventoryStock->quantity_reserved ?? 0
        );

        $reorderLevel = (int) (
            $this->inventoryStock->reorder_level ?? 0
        );

        $allowBackorder = (bool) (
            $this->inventoryStock->allow_backorder ?? false
        );

        $availableQuantity = max(
            0,
            $quantityOnHand - $quantityReserved
        );

        $stockStatus = match (true) {
            $availableQuantity <= 0 && !$allowBackorder =>
                'out_of_stock',

            $availableQuantity <= $reorderLevel =>
                'low_stock',

            default =>
                'in_stock',
        };

        return [
            'available_quantity' => $availableQuantity,

            'allow_backorder' => $allowBackorder,

            'is_available' => $availableQuantity > 0
                || $allowBackorder,

            'stock_status' => $stockStatus,
        ];
    }

    /**
     * Return public media attached to this variant.
     *
     * @return array<int, array<string, mixed>>
     */
    private function publicMedia(): array
    {
        return $this->media
            ->sortBy([
                ['is_primary', 'desc'],
                ['sort_order', 'asc'],
                ['created_at', 'asc'],
            ])
            ->values()
            ->map(
                static fn ($media): array => [
                    'public_id' => (string) $media->public_id,

                    'media_type' => is_object($media->media_type)
                        && property_exists(
                            $media->media_type,
                            'value'
                        )
                            ? $media->media_type->value
                            : (string) $media->media_type,

                    'url' => $media->url,

                    'alt_text' => $media->alt_text,

                    'is_primary' => (bool) $media->is_primary,

                    'sort_order' => (int) $media->sort_order,
                ]
            )
            ->all();
    }
}
