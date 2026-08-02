<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Throwable;

/**
 * @mixin Product
 */
final class PublicProductResource extends JsonResource
{
    /**
     * Transform an approved product into a customer-safe catalog response.
     *
     * @return array<string, mixed>
     */
    public function toArray(
        Request $request
    ): array {
        $media =
            $this->processedProductMedia();

        $primaryMedia =
            $this->primaryMedia($media);

        $variants =
            $this->publicVariants();

        $priceSummary =
            $this->priceSummary($variants);

        $inventorySummary =
            $this->inventorySummary($variants);

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

            'model_number' =>
                $this->nullableString(
                    $this->getAttribute(
                        'model_number'
                    )
                ),

            /*
            |--------------------------------------------------------------------------
            | Category and brand
            |--------------------------------------------------------------------------
            */

            'category' =>
                $this->categoryData(),

            'brand' =>
                $this->brandData(),

            /*
            |--------------------------------------------------------------------------
            | Approved seller
            |--------------------------------------------------------------------------
            |
            | Only customer-safe business identity is returned.
            |
            */

            'seller' =>
                $this->sellerData(),

            /*
            |--------------------------------------------------------------------------
            | Product card image
            |--------------------------------------------------------------------------
            |
            | The image URL points to a generated optimized rendition.
            | The original uploaded file is never used here.
            |
            */

            'image_url' =>
                $primaryMedia instanceof
                ProductMedia
                    ? $this->publicMediaUrl(
                        $primaryMedia,
                        'card'
                    )
                    : null,

            'primary_image' =>
                $primaryMedia instanceof
                ProductMedia
                    ? (
                        new PublicProductMediaResource(
                            $primaryMedia
                        )
                    )->resolve($request)
                    : null,

            /*
            |--------------------------------------------------------------------------
            | Processed product media
            |--------------------------------------------------------------------------
            */

            'media' =>
                PublicProductMediaResource::collection(
                    $media
                )->resolve($request),

            /*
            |--------------------------------------------------------------------------
            | Price summary
            |--------------------------------------------------------------------------
            */

            'price' =>
                $priceSummary,

            /*
            |--------------------------------------------------------------------------
            | Inventory summary
            |--------------------------------------------------------------------------
            */

            'inventory' =>
                $inventorySummary,

            /*
            |--------------------------------------------------------------------------
            | Purchasable active variants
            |--------------------------------------------------------------------------
            */

            'variants' =>
                $variants
                    ->map(
                        fn (
                            ProductVariant $variant
                        ): array =>
                            $this->variantData(
                                $variant,
                                $request
                            )
                    )
                    ->values()
                    ->all(),

            /*
            |--------------------------------------------------------------------------
            | Customer return policy
            |--------------------------------------------------------------------------
            */

            'return_policy' =>
                $this->returnPolicyData(),

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

    /*
    |--------------------------------------------------------------------------
    | Product relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Return public category information.
     *
     * @return array<string, mixed>|null
     */
    private function categoryData(): ?array
    {
        if (
            !$this->relationLoaded(
                'category'
            )
        ) {
            return null;
        }

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

    /**
     * Return public brand information.
     *
     * @return array<string, mixed>|null
     */
    private function brandData(): ?array
    {
        if (
            !$this->relationLoaded(
                'brand'
            )
        ) {
            return null;
        }

        $brand =
            $this->brand;

        if (!$brand instanceof Model) {
            return null;
        }

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

            'logo_path' =>
                $brand->getAttribute(
                    'logo_path'
                ),
        ];
    }

    /**
     * Return customer-safe approved seller information.
     *
     * @return array<string, mixed>|null
     */
    private function sellerData(): ?array
    {
        if (
            !$this->relationLoaded(
                'sellerProfile'
            )
        ) {
            return null;
        }

        $seller =
            $this->sellerProfile;

        if (!$seller instanceof Model) {
            return null;
        }

        $tradingName =
            $this->nullableString(
                $seller->getAttribute(
                    'trading_name'
                )
            );

        $legalName =
            $this->nullableString(
                $seller->getAttribute(
                    'legal_business_name'
                )
            );

        return [
            'public_id' =>
                (string) $seller
                    ->getAttribute(
                        'public_id'
                    ),

            'name' =>
                $tradingName
                ?? $legalName,

            'trading_name' =>
                $tradingName,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Product media
    |--------------------------------------------------------------------------
    */

    /**
     * Return only successfully processed product media.
     *
     * Pending, processing and failed files are excluded from public responses.
     *
     * @return Collection<int, ProductMedia>
     */
    private function processedProductMedia():
        Collection
    {
        if (
            !$this->relationLoaded(
                'media'
            )
        ) {
            return collect();
        }

        return collect(
            $this->media
        )
            ->filter(
                static fn (
                    mixed $media
                ): bool =>
                    $media instanceof
                        ProductMedia
                    && $media
                        ->isReadyForPublicUse()
            )
            ->sortBy(
                static fn (
                    ProductMedia $media
                ): array => [
                    $media->is_primary
                        ? 0
                        : 1,

                    (int) $media
                        ->sort_order,

                    (string) $media
                        ->getKey(),
                ]
            )
            ->values();
    }

    /**
     * Select the primary public product media.
     */
    private function primaryMedia(
        Collection $media
    ): ?ProductMedia {
        $primary =
            $media->first(
                static fn (
                    ProductMedia $media
                ): bool =>
                    (bool) $media
                        ->is_primary
            );

        if (
            $primary instanceof
            ProductMedia
        ) {
            return $primary;
        }

        $first =
            $media->first();

        return $first instanceof
            ProductMedia
                ? $first
                : null;
    }

    /**
     * Return a strictly optimized public media URL.
     *
     * This helper never falls back to the original upload.
     */
    private function publicMediaUrl(
        ProductMedia $media,
        string $context
    ): ?string {
        if (!$media->isCompleted()) {
            return null;
        }

        $renditionNames = match (
            strtolower(
                trim($context)
            )
        ) {
            'thumbnail' => [
                ProductMedia
                    ::RENDITION_THUMBNAIL,

                ProductMedia
                    ::RENDITION_CARD,

                ProductMedia
                    ::RENDITION_DETAIL,

                ProductMedia
                    ::RENDITION_ORIGINAL_OPTIMIZED,
            ],

            'detail' => [
                ProductMedia
                    ::RENDITION_DETAIL,

                ProductMedia
                    ::RENDITION_ORIGINAL_OPTIMIZED,

                ProductMedia
                    ::RENDITION_CARD,

                ProductMedia
                    ::RENDITION_THUMBNAIL,
            ],

            default => [
                ProductMedia
                    ::RENDITION_CARD,

                ProductMedia
                    ::RENDITION_DETAIL,

                ProductMedia
                    ::RENDITION_THUMBNAIL,

                ProductMedia
                    ::RENDITION_ORIGINAL_OPTIMIZED,
            ],
        };

        foreach (
            $renditionNames
            as $renditionName
        ) {
            $url =
                $media->renditionUrl(
                    $renditionName
                );

            if (
                is_string($url)
                && trim($url) !== ''
            ) {
                return trim($url);
            }
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | Variants
    |--------------------------------------------------------------------------
    */

    /**
     * Return active variants loaded by CatalogController.
     *
     * A variant is public only when it has a positive selling price.
     *
     * @return Collection<int, ProductVariant>
     */
    private function publicVariants():
        Collection
    {
        if (
            !$this->relationLoaded(
                'activeVariants'
            )
        ) {
            return collect();
        }

        return collect(
            $this->activeVariants
        )
            ->filter(
                function (
                    mixed $variant
                ): bool {
                    if (
                        !$variant instanceof
                        ProductVariant
                    ) {
                        return false;
                    }

                    if (
                        !$variant->relationLoaded(
                            'price'
                        )
                    ) {
                        return false;
                    }

                    $price =
                        $variant->price;

                    if (!$price instanceof Model) {
                        return false;
                    }

                    return (float) $price
                        ->getAttribute(
                            'selling_price'
                        ) > 0;
                }
            )
            ->sortBy(
                static fn (
                    ProductVariant $variant
                ): array => [
                    $variant->is_default
                        ? 0
                        : 1,

                    (int) $variant
                        ->sort_order,

                    (string) $variant
                        ->getKey(),
                ]
            )
            ->values();
    }

    /**
     * Transform one purchasable product variant.
     *
     * @return array<string, mixed>
     */
    private function variantData(
        ProductVariant $variant,
        Request $request
    ): array {
        $price =
            $variant->relationLoaded(
                'price'
            )
                ? $variant->price
                : null;

        $inventory =
            $variant->relationLoaded(
                'inventoryStock'
            )
                ? $variant
                    ->inventoryStock
                : null;

        $availableQuantity =
            $this->availableQuantity(
                $inventory
            );

        $allowBackorder =
            $inventory instanceof Model
                ? (bool) $inventory
                    ->getAttribute(
                        'allow_backorder'
                    )
                : false;

        $variantMedia =
            $this->processedVariantMedia(
                $variant
            );

        $primaryMedia =
            $this->primaryMedia(
                $variantMedia
            );

        return [
            'public_id' =>
                (string) $variant
                    ->public_id,

            'name' =>
                $variant->name,

            'sku' =>
                $variant->sku,

            'is_default' =>
                (bool) $variant
                    ->is_default,

            'sort_order' =>
                (int) $variant
                    ->sort_order,

            'attributes' =>
                is_array(
                    $variant
                        ->getAttribute(
                            'attributes'
                        )
                )
                    ? $variant
                        ->getAttribute(
                            'attributes'
                        )
                    : [],

            'price' =>
                $this->variantPriceData(
                    $price
                ),

            'inventory' => [
                'available_quantity' =>
                    $availableQuantity,

                'allow_backorder' =>
                    $allowBackorder,

                'is_available' =>
                    $availableQuantity > 0
                    || $allowBackorder,

                'stock_status' =>
                    $this->stockStatus(
                        $availableQuantity,
                        $allowBackorder
                    ),
            ],

            'image_url' =>
                $primaryMedia instanceof
                ProductMedia
                    ? $this->publicMediaUrl(
                        $primaryMedia,
                        'card'
                    )
                    : null,

            'media' =>
                PublicProductMediaResource::collection(
                    $variantMedia
                )->resolve($request),
        ];
    }

    /**
     * Return processed media belonging directly to one variant.
     *
     * @return Collection<int, ProductMedia>
     */
    private function processedVariantMedia(
        ProductVariant $variant
    ): Collection {
        if (
            !$variant->relationLoaded(
                'media'
            )
        ) {
            return collect();
        }

        return collect(
            $variant->media
        )
            ->filter(
                static fn (
                    mixed $media
                ): bool =>
                    $media instanceof
                        ProductMedia
                    && $media
                        ->isReadyForPublicUse()
            )
            ->sortBy(
                static fn (
                    ProductMedia $media
                ): array => [
                    $media->is_primary
                        ? 0
                        : 1,

                    (int) $media
                        ->sort_order,

                    (string) $media
                        ->getKey(),
                ]
            )
            ->values();
    }

    /*
    |--------------------------------------------------------------------------
    | Pricing
    |--------------------------------------------------------------------------
    */

    /**
     * Build public product price-range information.
     *
     * @param Collection<int, ProductVariant> $variants
     *
     * @return array<string, mixed>
     */
    private function priceSummary(
        Collection $variants
    ): array {
        $prices =
            $variants
                ->map(
                    function (
                        ProductVariant $variant
                    ): ?array {
                        if (
                            !$variant
                                ->relationLoaded(
                                    'price'
                                )
                        ) {
                            return null;
                        }

                        $price =
                            $variant->price;

                        if (!$price instanceof Model) {
                            return null;
                        }

                        $sellingPrice =
                            (float) $price
                                ->getAttribute(
                                    'selling_price'
                                );

                        if ($sellingPrice <= 0) {
                            return null;
                        }

                        return [
                            'amount' =>
                                $sellingPrice,

                            'currency' =>
                                strtoupper(
                                    (string) (
                                        $price
                                            ->getAttribute(
                                                'currency'
                                            )
                                        ?? 'RWF'
                                    )
                                ),
                        ];
                    }
                )
                ->filter()
                ->values();

        if ($prices->isEmpty()) {
            return [
                'minimum' =>
                    null,

                'maximum' =>
                    null,

                'currency' =>
                    null,

                'has_range' =>
                    false,

                'formatted' =>
                    null,
            ];
        }

        $minimum =
            (float) $prices->min(
                'amount'
            );

        $maximum =
            (float) $prices->max(
                'amount'
            );

        $currency =
            (string) (
                $prices
                    ->first()['currency']
                ?? 'RWF'
            );

        return [
            'minimum' =>
                $this->decimalValue(
                    $minimum
                ),

            'maximum' =>
                $this->decimalValue(
                    $maximum
                ),

            'currency' =>
                $currency,

            'has_range' =>
                $minimum !== $maximum,

            'formatted' =>
                $minimum === $maximum
                    ? sprintf(
                        '%s %s',
                        number_format(
                            $minimum,
                            2,
                            '.',
                            ''
                        ),
                        $currency
                    )
                    : sprintf(
                        '%s - %s %s',
                        number_format(
                            $minimum,
                            2,
                            '.',
                            ''
                        ),
                        number_format(
                            $maximum,
                            2,
                            '.',
                            ''
                        ),
                        $currency
                    ),
        ];
    }

    /**
     * Build public price information for one variant.
     *
     * Cost price is intentionally excluded.
     *
     * @return array<string, mixed>|null
     */
    private function variantPriceData(
        mixed $price
    ): ?array {
        if (!$price instanceof Model) {
            return null;
        }

        $sellingPrice =
            (float) $price
                ->getAttribute(
                    'selling_price'
                );

        if ($sellingPrice <= 0) {
            return null;
        }

        $compareAtPrice =
            $price->getAttribute(
                'compare_at_price'
            );

        $compareAtPrice =
            is_numeric($compareAtPrice)
                ? (float) $compareAtPrice
                : null;

        if (
            $compareAtPrice !== null
            && $compareAtPrice <=
                $sellingPrice
        ) {
            $compareAtPrice = null;
        }

        $discountAmount =
            $compareAtPrice !== null
                ? $compareAtPrice
                    - $sellingPrice
                : null;

        $discountPercentage =
            $compareAtPrice !== null
            && $compareAtPrice > 0
                ? round(
                    (
                        (
                            $compareAtPrice
                            - $sellingPrice
                        )
                        / $compareAtPrice
                    ) * 100,
                    2
                )
                : null;

        return [
            'selling_price' =>
                $this->decimalValue(
                    $sellingPrice
                ),

            'compare_at_price' =>
                $compareAtPrice !== null
                    ? $this->decimalValue(
                        $compareAtPrice
                    )
                    : null,

            'currency' =>
                strtoupper(
                    (string) (
                        $price->getAttribute(
                            'currency'
                        )
                        ?? 'RWF'
                    )
                ),

            'has_discount' =>
                $compareAtPrice !== null,

            'discount_amount' =>
                $discountAmount !== null
                    ? $this->decimalValue(
                        $discountAmount
                    )
                    : null,

            'discount_percentage' =>
                $discountPercentage,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Inventory
    |--------------------------------------------------------------------------
    */

    /**
     * Build product-level stock information.
     *
     * @param Collection<int, ProductVariant> $variants
     *
     * @return array<string, mixed>
     */
    private function inventorySummary(
        Collection $variants
    ): array {
        $totalAvailable = 0;
        $allowsBackorder = false;
        $availableVariantCount = 0;

        foreach ($variants as $variant) {
            $inventory =
                $variant->relationLoaded(
                    'inventoryStock'
                )
                    ? $variant
                        ->inventoryStock
                    : null;

            $available =
                $this->availableQuantity(
                    $inventory
                );

            $backorder =
                $inventory instanceof Model
                    ? (bool) $inventory
                        ->getAttribute(
                            'allow_backorder'
                        )
                    : false;

            $totalAvailable +=
                $available;

            $allowsBackorder =
                $allowsBackorder
                || $backorder;

            if (
                $available > 0
                || $backorder
            ) {
                $availableVariantCount++;
            }
        }

        return [
            'is_available' =>
                $totalAvailable > 0
                || $allowsBackorder,

            'in_stock' =>
                $totalAvailable > 0,

            'allow_backorder' =>
                $allowsBackorder,

            'available_quantity' =>
                $totalAvailable,

            'available_variants_count' =>
                $availableVariantCount,

            'stock_status' =>
                $this->stockStatus(
                    $totalAvailable,
                    $allowsBackorder
                ),
        ];
    }

    /**
     * Calculate available inventory without exposing reserved quantity.
     */
    private function availableQuantity(
        mixed $inventory
    ): int {
        if (!$inventory instanceof Model) {
            return 0;
        }

        $onHand = max(
            0,
            (int) $inventory
                ->getAttribute(
                    'quantity_on_hand'
                )
        );

        $reserved = max(
            0,
            (int) $inventory
                ->getAttribute(
                    'quantity_reserved'
                )
        );

        return max(
            0,
            $onHand - $reserved
        );
    }

    /**
     * Return a customer-readable stock status.
     */
    private function stockStatus(
        int $availableQuantity,
        bool $allowBackorder
    ): string {
        if ($availableQuantity > 0) {
            return 'in_stock';
        }

        if ($allowBackorder) {
            return 'available_for_backorder';
        }

        return 'out_of_stock';
    }

    /*
    |--------------------------------------------------------------------------
    | Return policy
    |--------------------------------------------------------------------------
    */

    /**
     * Return customer-safe active return-policy information.
     *
     * @return array<string, mixed>|null
     */
    private function returnPolicyData(): ?array
    {
        $relationName = null;

        if (
            $this->relationLoaded(
                'activeReturnPolicy'
            )
        ) {
            $relationName =
                'activeReturnPolicy';
        } elseif (
            $this->relationLoaded(
                'returnPolicy'
            )
        ) {
            $relationName =
                'returnPolicy';
        }

        if ($relationName === null) {
            return null;
        }

        $policy =
            $this->getRelation(
                $relationName
            );

        if (!$policy instanceof Model) {
            return null;
        }

        if (
            method_exists(
                $policy,
                'toCustomerPolicy'
            )
        ) {
            try {
                $customerPolicy =
                    $policy
                        ->toCustomerPolicy();

                if (
                    is_array(
                        $customerPolicy
                    )
                ) {
                    return $customerPolicy;
                }
            } catch (Throwable) {
                return null;
            }
        }

        return [
            'allows_returns' =>
                (bool) $policy
                    ->getAttribute(
                        'allows_returns'
                    ),

            'allows_refunds' =>
                (bool) $policy
                    ->getAttribute(
                        'allows_refunds'
                    ),

            'allows_exchanges' =>
                (bool) $policy
                    ->getAttribute(
                        'allows_exchanges'
                    ),

            'return_window_days' =>
                $policy->getAttribute(
                    'return_window_days'
                ) !== null
                    ? (int) $policy
                        ->getAttribute(
                            'return_window_days'
                        )
                    : null,

            'conditions' =>
                is_array(
                    $policy->getAttribute(
                        'conditions'
                    )
                )
                    ? $policy->getAttribute(
                        'conditions'
                    )
                    : [],

            'refund_methods' =>
                is_array(
                    $policy->getAttribute(
                        'refund_methods'
                    )
                )
                    ? $policy->getAttribute(
                        'refund_methods'
                    )
                    : [],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Value helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Convert an enum or scalar into a public string.
     */
    private function enumValue(
        mixed $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        if ($value instanceof BackedEnum) {
            return (string) $value
                ->value;
        }

        return $this->nullableString(
            $value
        );
    }

    /**
     * Normalize optional text.
     */
    private function nullableString(
        mixed $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value = trim(
            (string) $value
        );

        return $value !== ''
            ? $value
            : null;
    }

    /**
     * Return a stable two-decimal monetary value.
     */
    private function decimalValue(
        float|int|string $value
    ): string {
        return number_format(
            (float) $value,
            2,
            '.',
            ''
        );
    }

    /**
     * Convert a timestamp into ISO-8601.
     */
    private function dateValue(
        mixed $value
    ): ?string {
        if ($value instanceof CarbonInterface) {
            return $value
                ->toISOString();
        }

        if (
            is_string($value)
            && trim($value) !== ''
        ) {
            return trim($value);
        }

        return null;
    }
}