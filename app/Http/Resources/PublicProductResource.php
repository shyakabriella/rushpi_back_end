<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Product;
use App\Models\ProductReturnPolicy;
use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * @mixin Product
 */
final class PublicProductResource extends JsonResource
{
    /**
     * Transform the approved product into customer-safe catalog data.
     *
     * @return array<string, mixed>
     */
    public function toArray(
        Request $request
    ): array {
        $variants = $this->loadedActiveVariants();

        $media = $this->loadedProductMedia();

        return [
            /*
            |--------------------------------------------------------------------------
            | Product identity
            |--------------------------------------------------------------------------
            */

            'public_id' =>
                (string) $this->public_id,

            'name' =>
                (string) $this->name,

            'slug' =>
                (string) $this->slug,

            'short_description' =>
                $this->short_description,

            'description' =>
                $this->description,

            'condition' =>
                $this->enumValue(
                    $this->condition
                ),

            'warranty_months' =>
                $this->warranty_months !== null
                    ? (int) $this->warranty_months
                    : null,

            /*
            |--------------------------------------------------------------------------
            | Product specifications
            |--------------------------------------------------------------------------
            */

            'specifications' =>
                $this->specificationValues(),

            /*
            |--------------------------------------------------------------------------
            | Category
            |--------------------------------------------------------------------------
            */

            'category' =>
                $this->whenLoaded(
                    'category',
                    function (): ?array {
                        $category =
                            $this->category;

                        if (!$category instanceof Model) {
                            return null;
                        }

                        return [
                            'public_id' =>
                                (string) $category
                                    ->getAttribute(
                                        'public_id'
                                    ),

                            'name' =>
                                (string) $category
                                    ->getAttribute(
                                        'name'
                                    ),

                            'slug' =>
                                (string) $category
                                    ->getAttribute(
                                        'slug'
                                    ),
                        ];
                    }
                ),

            /*
            |--------------------------------------------------------------------------
            | Brand
            |--------------------------------------------------------------------------
            */

            'brand' =>
                $this->whenLoaded(
                    'brand',
                    function (): ?array {
                        $brand =
                            $this->brand;

                        if (!$brand instanceof Model) {
                            return null;
                        }

                        $logoPath =
                            $brand->getAttribute(
                                'logo_path'
                            );

                        return [
                            'public_id' =>
                                (string) $brand
                                    ->getAttribute(
                                        'public_id'
                                    ),

                            'name' =>
                                (string) $brand
                                    ->getAttribute(
                                        'name'
                                    ),

                            'slug' =>
                                (string) $brand
                                    ->getAttribute(
                                        'slug'
                                    ),

                            'logo_url' =>
                                $this->publicStorageUrl(
                                    is_string($logoPath)
                                        ? $logoPath
                                        : null
                                ),
                        ];
                    }
                ),

            /*
            |--------------------------------------------------------------------------
            | Verified seller
            |--------------------------------------------------------------------------
            |
            | Registration numbers, tax numbers, contact details, account
            | members and verification documents are intentionally excluded.
            |
            */

            'seller' =>
                $this->whenLoaded(
                    'sellerProfile',
                    function (): ?array {
                        $seller =
                            $this->sellerProfile;

                        if (!$seller instanceof Model) {
                            return null;
                        }

                        $tradingName = trim(
                            (string) (
                                $seller->getAttribute(
                                    'trading_name'
                                ) ?? ''
                            )
                        );

                        $legalName = trim(
                            (string) (
                                $seller->getAttribute(
                                    'legal_business_name'
                                ) ?? ''
                            )
                        );

                        return [
                            'public_id' =>
                                (string) $seller
                                    ->getAttribute(
                                        'public_id'
                                    ),

                            'name' =>
                                $tradingName !== ''
                                    ? $tradingName
                                    : $legalName,

                            'is_verified' =>
                                true,
                        ];
                    }
                ),

            /*
            |--------------------------------------------------------------------------
            | Customer return policy
            |--------------------------------------------------------------------------
            |
            | Only the active customer-facing policy is returned. Audit users,
            | internal product information and configuration errors are not
            | exposed publicly.
            |
            */

            'return_policy' =>
                $this->publicReturnPolicy(),

            /*
            |--------------------------------------------------------------------------
            | Pricing summary
            |--------------------------------------------------------------------------
            */

            'pricing' =>
                $this->pricingSummary(
                    $variants
                ),

            /*
            |--------------------------------------------------------------------------
            | Availability summary
            |--------------------------------------------------------------------------
            */

            'availability' =>
                $this->availabilitySummary(
                    $variants
                ),

            /*
            |--------------------------------------------------------------------------
            | Active variants
            |--------------------------------------------------------------------------
            */

            'variants' =>
                $variants
                    ->map(
                        fn (
                            Model $variant
                        ): array =>
                            $this->variantData(
                                $variant
                            )
                    )
                    ->values()
                    ->all(),

            /*
            |--------------------------------------------------------------------------
            | Product media
            |--------------------------------------------------------------------------
            */

            'media' =>
                $media
                    ->map(
                        fn (
                            Model $mediaItem
                        ): array =>
                            $this->mediaData(
                                $mediaItem
                            )
                    )
                    ->values()
                    ->all(),

            'primary_media' =>
                $this->primaryMediaData(
                    $media
                ),

            /*
            |--------------------------------------------------------------------------
            | Public timestamps
            |--------------------------------------------------------------------------
            */

            'approved_at' =>
                $this->dateValue(
                    $this->getAttribute(
                        'approved_at'
                    )
                ),

            'created_at' =>
                $this->dateValue(
                    $this->created_at
                ),

            'updated_at' =>
                $this->dateValue(
                    $this->updated_at
                ),
        ];
    }

    /**
     * Transform one active product variant.
     *
     * Cost prices and internal stock configuration are excluded.
     *
     * @return array<string, mixed>
     */
    private function variantData(
        Model $variant
    ): array {
        $price = $variant
            ->relationLoaded('price')
                ? $variant->getRelation(
                    'price'
                )
                : null;

        $inventory = $variant
            ->relationLoaded(
                'inventoryStock'
            )
                ? $variant->getRelation(
                    'inventoryStock'
                )
                : null;

        $inventoryData =
            $inventory instanceof Model
                ? $this->publicInventoryData(
                    $inventory
                )
                : [
                    'is_in_stock' => false,
                    'is_low_stock' => false,
                    'allow_backorder' => false,
                    'available_quantity' => 0,
                ];

        $variantMedia = $variant
            ->relationLoaded('media')
                ? $variant->getRelation(
                    'media'
                )
                : collect();

        if (!$variantMedia instanceof Collection) {
            $variantMedia = collect();
        }

        $transformedMedia =
            $variantMedia
                ->map(
                    fn (
                        Model $media
                    ): array =>
                        $this->mediaData(
                            $media
                        )
                )
                ->values()
                ->all();

        $primaryMedia =
            $variantMedia
                ->first(
                    static fn (
                        Model $media
                    ): bool =>
                        (bool) $media
                            ->getAttribute(
                                'is_primary'
                            )
                )
            ?? $variantMedia->first();

        return [
            'public_id' =>
                (string) $variant
                    ->getAttribute(
                        'public_id'
                    ),

            'sku' =>
                $variant->getAttribute(
                    'sku'
                ),

            'name' =>
                $variant->getAttribute(
                    'name'
                ),

            'attributes' =>
                $variant->getAttribute(
                    'attributes'
                ) ?? [],

            'is_default' =>
                (bool) $variant
                    ->getAttribute(
                        'is_default'
                    ),

            'price' =>
                $price instanceof Model
                    ? $this->publicPriceData(
                        $price
                    )
                    : null,

            'availability' =>
                $inventoryData,

            'media' =>
                $transformedMedia,

            'primary_media' =>
                $primaryMedia instanceof Model
                    ? $this->mediaData(
                        $primaryMedia
                    )
                    : null,
        ];
    }

    /**
     * Transform a variant price without exposing product cost.
     *
     * @return array<string, mixed>
     */
    private function publicPriceData(
        Model $price
    ): array {
        $sellingPrice =
            $this->numericValue(
                $price->getAttribute(
                    'selling_price'
                )
            );

        $compareAtPrice =
            $this->numericValue(
                $price->getAttribute(
                    'compare_at_price'
                )
            );

        $currency =
            $price->getAttribute(
                'currency'
            )
            ?? $price->getAttribute(
                'currency_code'
            )
            ?? 'RWF';

        $discountAmount = null;
        $discountPercent = null;

        if (
            $sellingPrice !== null
            && $compareAtPrice !== null
            && $compareAtPrice > $sellingPrice
            && $compareAtPrice > 0
        ) {
            $discountAmount = round(
                $compareAtPrice
                - $sellingPrice,
                2
            );

            $discountPercent = round(
                (
                    $discountAmount
                    / $compareAtPrice
                ) * 100,
                2
            );
        }

        return [
            'currency' =>
                (string) $currency,

            'selling_price' =>
                $sellingPrice,

            'compare_at_price' =>
                $compareAtPrice,

            'discount' => [
                'amount' =>
                    $discountAmount,

                'percent' =>
                    $discountPercent,
            ],
        ];
    }

    /**
     * Transform inventory into customer-safe availability information.
     *
     * Internal restock levels and reserved quantities are excluded.
     *
     * @return array<string, mixed>
     */
    private function publicInventoryData(
        Model $inventory
    ): array {
        $quantityOnHand = (int) (
            $inventory->getAttribute(
                'quantity_on_hand'
            ) ?? 0
        );

        $quantityReserved = (int) (
            $inventory->getAttribute(
                'quantity_reserved'
            ) ?? 0
        );

        $availableQuantity = max(
            $quantityOnHand
            - $quantityReserved,
            0
        );

        $allowBackorder = (bool) (
            $inventory->getAttribute(
                'allow_backorder'
            ) ?? false
        );

        $lowStockThreshold = (int) (
            $inventory->getAttribute(
                'low_stock_threshold'
            )
            ?? $inventory->getAttribute(
                'reorder_level'
            )
            ?? 0
        );

        return [
            'is_in_stock' =>
                $availableQuantity > 0
                || $allowBackorder,

            'is_low_stock' =>
                $availableQuantity > 0
                && $lowStockThreshold > 0
                && $availableQuantity
                    <= $lowStockThreshold,

            'allow_backorder' =>
                $allowBackorder,

            'available_quantity' =>
                $availableQuantity,
        ];
    }

    /**
     * Transform customer-safe product media.
     *
     * Storage disk names and internal paths are excluded.
     *
     * @return array<string, mixed>
     */
    private function mediaData(
        Model $media
    ): array {
        $path =
            $media->getAttribute(
                'storage_path'
            )
            ?? $media->getAttribute(
                'path'
            );

        $disk =
            $media->getAttribute(
                'storage_disk'
            )
            ?? $media->getAttribute(
                'disk'
            )
            ?? 'public';

        $variantPublicId = null;

        if (
            $media->relationLoaded(
                'variant'
            )
        ) {
            $variant =
                $media->getRelation(
                    'variant'
                );

            if ($variant instanceof Model) {
                $variantPublicId =
                    (string) $variant
                        ->getAttribute(
                            'public_id'
                        );
            }
        }

        return [
            'public_id' =>
                (string) $media
                    ->getAttribute(
                        'public_id'
                    ),

            'variant_public_id' =>
                $variantPublicId,

            'media_type' =>
                $this->enumValue(
                    $media->getAttribute(
                        'media_type'
                    )
                ),

            'url' =>
                $this->mediaUrl(
                    media: $media,
                    disk: (string) $disk,
                    path: is_string($path)
                        ? $path
                        : null
                ),

            'alt_text' =>
                $media->getAttribute(
                    'alt_text'
                ),

            'mime_type' =>
                $media->getAttribute(
                    'mime_type'
                ),

            'is_primary' =>
                (bool) $media
                    ->getAttribute(
                        'is_primary'
                    ),

            'sort_order' =>
                (int) (
                    $media->getAttribute(
                        'sort_order'
                    ) ?? 0
                ),
        ];
    }

    /**
     * Build the product-level pricing summary.
     *
     * @param Collection<int, Model> $variants
     *
     * @return array<string, mixed>
     */
    private function pricingSummary(
        Collection $variants
    ): array {
        $prices = $variants
            ->map(
                static function (
                    Model $variant
                ): ?Model {
                    if (
                        !$variant
                            ->relationLoaded(
                                'price'
                            )
                    ) {
                        return null;
                    }

                    $price = $variant
                        ->getRelation(
                            'price'
                        );

                    return $price instanceof Model
                        ? $price
                        : null;
                }
            )
            ->filter(
                static fn (
                    mixed $price
                ): bool => $price
                    instanceof Model
                    && is_numeric(
                        $price->getAttribute(
                            'selling_price'
                        )
                    )
                    && (float) $price
                        ->getAttribute(
                            'selling_price'
                        ) > 0
            )
            ->values();

        if ($prices->isEmpty()) {
            return [
                'currency' => null,
                'minimum_price' => null,
                'maximum_price' => null,
                'has_price_range' => false,
            ];
        }

        $sellingPrices = $prices
            ->map(
                static fn (
                    Model $price
                ): float => (float) $price
                    ->getAttribute(
                        'selling_price'
                    )
            );

        $firstPrice = $prices->first();

        $currency =
            $firstPrice instanceof Model
                ? (
                    $firstPrice->getAttribute(
                        'currency'
                    )
                    ?? $firstPrice->getAttribute(
                        'currency_code'
                    )
                    ?? 'RWF'
                )
                : 'RWF';

        $minimum = (float) $sellingPrices
            ->min();

        $maximum = (float) $sellingPrices
            ->max();

        return [
            'currency' =>
                (string) $currency,

            'minimum_price' =>
                $minimum,

            'maximum_price' =>
                $maximum,

            'has_price_range' =>
                $minimum !== $maximum,
        ];
    }

    /**
     * Build the product-level stock summary.
     *
     * @param Collection<int, Model> $variants
     *
     * @return array<string, mixed>
     */
    private function availabilitySummary(
        Collection $variants
    ): array {
        $activeVariantsCount =
            $variants->count();

        $inStockVariants = 0;
        $backorderVariants = 0;
        $availableQuantity = 0;

        foreach ($variants as $variant) {
            if (
                !$variant->relationLoaded(
                    'inventoryStock'
                )
            ) {
                continue;
            }

            $inventory =
                $variant->getRelation(
                    'inventoryStock'
                );

            if (!$inventory instanceof Model) {
                continue;
            }

            $data =
                $this->publicInventoryData(
                    $inventory
                );

            if ($data['is_in_stock']) {
                $inStockVariants++;
            }

            if ($data['allow_backorder']) {
                $backorderVariants++;
            }

            $availableQuantity +=
                (int) $data[
                    'available_quantity'
                ];
        }

        return [
            'is_in_stock' =>
                $inStockVariants > 0,

            'available_quantity' =>
                $availableQuantity,

            'active_variants_count' =>
                $activeVariantsCount,

            'in_stock_variants_count' =>
                $inStockVariants,

            'backorder_variants_count' =>
                $backorderVariants,
        ];
    }

    /**
     * Return the active customer-facing return policy without triggering a
     * database query from inside the resource.
     *
     * @return array<string, mixed>|null
     */
    private function publicReturnPolicy(): ?array
    {
        $policy = null;

        if (
            $this->resource
                ->relationLoaded(
                    'activeReturnPolicy'
                )
        ) {
            $loadedPolicy =
                $this->resource
                    ->getRelation(
                        'activeReturnPolicy'
                    );

            if (
                $loadedPolicy
                instanceof ProductReturnPolicy
            ) {
                $policy = $loadedPolicy;
            }
        }

        /*
         * This fallback supports callers that loaded returnPolicy rather than
         * activeReturnPolicy.
         */

        if (
            !$policy instanceof
            ProductReturnPolicy
            && $this->resource
                ->relationLoaded(
                    'returnPolicy'
                )
        ) {
            $loadedPolicy =
                $this->resource
                    ->getRelation(
                        'returnPolicy'
                    );

            if (
                $loadedPolicy
                    instanceof ProductReturnPolicy
                && $loadedPolicy->is_active
            ) {
                $policy = $loadedPolicy;
            }
        }

        if (
            !$policy instanceof
            ProductReturnPolicy
        ) {
            return null;
        }

        return $policy->toCustomerPolicy();
    }

    /**
     * Return loaded active variants without triggering another query.
     *
     * @return Collection<int, Model>
     */
    private function loadedActiveVariants(): Collection
    {
        if (
            !$this->resource
                ->relationLoaded(
                    'activeVariants'
                )
        ) {
            return collect();
        }

        $variants =
            $this->resource
                ->getRelation(
                    'activeVariants'
                );

        return $variants instanceof Collection
            ? $variants
            : collect();
    }

    /**
     * Return loaded product media without triggering another query.
     *
     * @return Collection<int, Model>
     */
    private function loadedProductMedia(): Collection
    {
        if (
            !$this->resource
                ->relationLoaded('media')
        ) {
            return collect();
        }

        $media =
            $this->resource
                ->getRelation('media');

        return $media instanceof Collection
            ? $media
            : collect();
    }

    /**
     * Select the primary product media.
     *
     * @param Collection<int, Model> $media
     *
     * @return array<string, mixed>|null
     */
    private function primaryMediaData(
        Collection $media
    ): ?array {
        $primary = $media
            ->first(
                static fn (
                    Model $mediaItem
                ): bool => (bool) $mediaItem
                    ->getAttribute(
                        'is_primary'
                    )
            )
            ?? $media->first();

        return $primary instanceof Model
            ? $this->mediaData(
                $primary
            )
            : null;
    }

    /**
     * Return a media URL without allowing storage failures to break the
     * public catalog.
     */
    private function mediaUrl(
        Model $media,
        string $disk,
        ?string $path
    ): ?string {
        $existingUrl = $media
            ->getAttribute('url');

        if (
            is_string($existingUrl)
            && trim($existingUrl) !== ''
        ) {
            return $existingUrl;
        }

        if (
            $path === null
            || trim($path) === ''
        ) {
            return null;
        }

        try {
            return Storage::disk(
                $disk
            )->url($path);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Return a URL from the public storage disk.
     */
    private function publicStorageUrl(
        ?string $path
    ): ?string {
        if (
            $path === null
            || trim($path) === ''
        ) {
            return null;
        }

        try {
            return Storage::disk(
                'public'
            )->url($path);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Convert enums and scalar values into API strings.
     */
    private function enumValue(
        mixed $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        $value = trim(
            (string) $value
        );

        return $value !== ''
            ? $value
            : null;
    }

    /**
     * Convert number-like values into floats.
     */
    private function numericValue(
        mixed $value
    ): ?float {
        return is_numeric($value)
            ? (float) $value
            : null;
    }

    /**
     * Convert date values into ISO-8601 strings.
     */
    private function dateValue(
        mixed $value
    ): ?string {
        if ($value instanceof CarbonInterface) {
            return $value->toISOString();
        }

        if (
            is_string($value)
            && trim($value) !== ''
        ) {
            return $value;
        }

        return null;
    }
}