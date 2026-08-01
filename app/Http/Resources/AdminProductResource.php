<?php

declare(strict_types=1);

namespace App\Http\Resources;

use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use UnitEnum;

/**
 * @mixin \App\Models\Product
 */
class AdminProductResource extends JsonResource
{
    /**
     * Transform the product into an administrator API response.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'public_id' => $this->public_id,

            'name' => $this->name,

            'slug' => $this->slug,

            'short_description' =>
                $this->short_description,

            'description' =>
                $this->description,

            'condition' => [
                'value' =>
                    $this->enumValue($this->condition),

                'label' =>
                    $this->enumLabel($this->condition),
            ],

            'warranty_months' =>
                $this->warranty_months !== null
                    ? (int) $this->warranty_months
                    : null,

            'specifications' =>
                $this->specifications ?? [],

            'status' => [
                'value' =>
                    $this->enumValue($this->status),

                'label' =>
                    $this->enumLabel($this->status),
            ],

            /*
             * Seller information is returned when loaded
             * by the administrator controller.
             */
            'seller' => $this->whenLoaded(
                'sellerProfile',
                function (): ?array {
                    if ($this->sellerProfile === null) {
                        return null;
                    }

                    return [
                        'public_id' =>
                            $this->sellerProfile->public_id,

                        'legal_business_name' =>
                            $this->sellerProfile
                                ->legal_business_name,

                        'trading_name' =>
                            $this->sellerProfile
                                ->trading_name,

                        'registration_number' =>
                            $this->sellerProfile
                                ->registration_number,

                        'tax_identification_number' =>
                            $this->sellerProfile
                                ->tax_identification_number,

                        'business_email' =>
                            $this->sellerProfile
                                ->business_email,

                        'business_phone' =>
                            $this->sellerProfile
                                ->business_phone,

                        'status' =>
                            $this->enumValue(
                                $this->sellerProfile->status
                            ),
                    ];
                }
            ),

            'category' => $this->whenLoaded(
                'category',
                function (): ?array {
                    if ($this->category === null) {
                        return null;
                    }

                    return [
                        'public_id' =>
                            $this->category->public_id,

                        'name' =>
                            $this->category->name,

                        'slug' =>
                            $this->category->slug,

                        'is_active' =>
                            (bool) $this->category->is_active,
                    ];
                }
            ),

            'brand' => $this->whenLoaded(
                'brand',
                function (): ?array {
                    if ($this->brand === null) {
                        return null;
                    }

                    return [
                        'public_id' =>
                            $this->brand->public_id,

                        'name' =>
                            $this->brand->name,

                        'slug' =>
                            $this->brand->slug,

                        'logo_path' =>
                            $this->brand->logo_path,

                        'website_url' =>
                            $this->brand->website_url,

                        'is_active' =>
                            (bool) $this->brand->is_active,
                    ];
                }
            ),

            /*
             * Complete variant details, including private cost
             * price and operational inventory information.
             */
            'variants' => $this->whenLoaded(
                'variants',
                function () {
                    return $this->variants
                        ->map(function ($variant): array {
                            return [
                                'public_id' =>
                                    $variant->public_id,

                                'sku' =>
                                    $variant->sku,

                                'barcode' =>
                                    $variant->barcode,

                                'name' =>
                                    $variant->name,

                                'attributes' =>
                                    $variant->attributes ?? [],

                                'dimensions' => [
                                    'weight_grams' =>
                                        $variant->weight_grams !== null
                                            ? (int) $variant->weight_grams
                                            : null,

                                    'length_cm' =>
                                        $variant->length_cm,

                                    'width_cm' =>
                                        $variant->width_cm,

                                    'height_cm' =>
                                        $variant->height_cm,
                                ],

                                'is_default' =>
                                    (bool) $variant->is_default,

                                'is_active' =>
                                    (bool) $variant->is_active,

                                'sort_order' =>
                                    (int) $variant->sort_order,

                                'price' =>
                                    $variant->relationLoaded('price')
                                        ? $this->variantPrice(
                                            $variant->price
                                        )
                                        : null,

                                'inventory' =>
                                    $variant->relationLoaded(
                                        'inventoryStock'
                                    )
                                        ? $this->variantInventory(
                                            $variant->inventoryStock
                                        )
                                        : null,

                                'media' =>
                                    $variant->relationLoaded('media')
                                        ? $variant->media
                                            ->map(
                                                fn ($media): array =>
                                                    $this->mediaData(
                                                        $media
                                                    )
                                            )
                                            ->values()
                                            ->all()
                                        : [],
                            ];
                        })
                        ->values();
                }
            ),

            /*
             * General product media, including images attached
             * directly to variants.
             */
            'media' => $this->whenLoaded(
                'media',
                function () {
                    return $this->media
                        ->map(
                            fn ($media): array =>
                                $this->mediaData($media)
                        )
                        ->values();
                }
            ),

            /*
             * Full moderation audit history.
             *
             * Internal notes are returned because this resource
             * is restricted to administrators.
             */
            'moderation_reviews' => $this->whenLoaded(
                'moderationReviews',
                function () {
                    return $this->moderationReviews
                        ->map(function ($review): array {
                            return [
                                'public_id' =>
                                    $review->public_id,

                                'action' => [
                                    'value' =>
                                        $this->enumValue(
                                            $review->action
                                        ),

                                    'label' =>
                                        $this->enumLabel(
                                            $review->action
                                        ),
                                ],

                                'reason' =>
                                    $review->reason,

                                'internal_notes' =>
                                    $review->internal_notes,

                                'snapshot' =>
                                    $review->snapshot ?? [],

                                'reviewed_by' =>
                                    $review->relationLoaded(
                                        'reviewedBy'
                                    )
                                        && $review->reviewedBy !== null
                                            ? [
                                                'public_id' =>
                                                    $review
                                                        ->reviewedBy
                                                        ->public_id,

                                                'name' =>
                                                    $review
                                                        ->reviewedBy
                                                        ->name,

                                                'email' =>
                                                    $review
                                                        ->reviewedBy
                                                        ->email,
                                            ]
                                            : null,

                                'created_at' =>
                                    $review->created_at
                                        ?->toISOString(),
                            ];
                        })
                        ->values();
                }
            ),

            /*
             * Current moderation decision information.
             */
            'moderation' => [
                'rejection_reason' =>
                    $this->rejection_reason,

                'suspension_reason' =>
                    $this->suspension_reason,

                'submitted_at' =>
                    $this->submitted_at?->toISOString(),

                'approved_at' =>
                    $this->approved_at?->toISOString(),

                'rejected_at' =>
                    $this->rejected_at?->toISOString(),

                'suspended_at' =>
                    $this->suspended_at?->toISOString(),

                'archived_at' =>
                    $this->archived_at?->toISOString(),
            ],

            /*
             * Product activity users.
             */
            'created_by' =>
                $this->userData('createdBy'),

            'updated_by' =>
                $this->userData('updatedBy'),

            'approved_by' =>
                $this->userData('approvedBy'),

            'counts' => [
                'variants' =>
                    $this->whenCounted('variants'),

                'active_variants' =>
                    $this->whenCounted(
                        'activeVariants'
                    ),

                'media' =>
                    $this->whenCounted('media'),

                'moderation_reviews' =>
                    $this->whenCounted(
                        'moderationReviews'
                    ),
            ],

            'created_at' =>
                $this->created_at?->toISOString(),

            'updated_at' =>
                $this->updated_at?->toISOString(),

            'deleted_at' =>
                $this->deleted_at?->toISOString(),
        ];
    }

    /**
     * Transform variant pricing.
     *
     * @return array<string, mixed>|null
     */
    private function variantPrice(
        mixed $price
    ): ?array {
        if ($price === null) {
            return null;
        }

        return [
            'currency' =>
                $price->currency,

            'selling_price' =>
                $price->selling_price,

            'compare_at_price' =>
                $price->compare_at_price,

            'cost_price' =>
                $price->cost_price,

            'is_discounted' =>
                $price->isDiscounted(),

            'discount_percentage' =>
                $price->discountPercentage(),

            'formatted_selling_price' =>
                $price->formattedSellingPrice(),
        ];
    }

    /**
     * Transform variant inventory.
     *
     * @return array<string, mixed>|null
     */
    private function variantInventory(
        mixed $inventory
    ): ?array {
        if ($inventory === null) {
            return null;
        }

        return [
            'quantity_on_hand' =>
                (int) $inventory->quantity_on_hand,

            'quantity_reserved' =>
                (int) $inventory->quantity_reserved,

            'available_quantity' =>
                $inventory->availableQuantity(),

            'reorder_level' =>
                (int) $inventory->reorder_level,

            'allow_backorder' =>
                (bool) $inventory->allow_backorder,

            'stock_status' =>
                $inventory->stockStatus(),

            'is_in_stock' =>
                $inventory->isInStock(),

            'is_low_stock' =>
                $inventory->isLowStock(),

            'is_out_of_stock' =>
                $inventory->isOutOfStock(),
        ];
    }

    /**
     * Transform product media.
     *
     * @return array<string, mixed>
     */
    private function mediaData(
        mixed $media
    ): array {
        return [
            'public_id' =>
                $media->public_id,

            'variant_public_id' =>
                $media->relationLoaded('variant')
                    ? $media->variant?->public_id
                    : null,

            'media_type' =>
                $this->enumValue(
                    $media->media_type
                ),

            'original_name' =>
                $media->original_name,

            'mime_type' =>
                $media->mime_type,

            'extension' =>
                $media->extension,

            'size_bytes' =>
                $media->size_bytes !== null
                    ? (int) $media->size_bytes
                    : null,

            'alt_text' =>
                $media->alt_text,

            'sort_order' =>
                (int) $media->sort_order,

            'is_primary' =>
                (bool) $media->is_primary,

            'url' =>
                $media->url(),

            'file_exists' =>
                $media->exists(),
        ];
    }

    /**
     * Return user information when a relationship is loaded.
     *
     * @return array<string, mixed>|null
     */
    private function userData(
        string $relationship
    ): ?array {
        if (! $this->relationLoaded($relationship)) {
            return null;
        }

        $user = $this->{$relationship};

        if ($user === null) {
            return null;
        }

        return [
            'public_id' =>
                $user->public_id,

            'name' =>
                $user->name,

            'email' =>
                $user->email,
        ];
    }

    /**
     * Return an enum or string value.
     */
    private function enumValue(
        mixed $value
    ): mixed {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        return $value;
    }

    /**
     * Return a human-readable enum label.
     */
    private function enumLabel(
        mixed $value
    ): ?string {
        if (
            is_object($value)
            && method_exists($value, 'label')
        ) {
            return $value->label();
        }

        $enumValue = $this->enumValue($value);

        if (! is_string($enumValue)) {
            return null;
        }

        return str($enumValue)
            ->replace(['_', '-'], ' ')
            ->title()
            ->toString();
    }
}
