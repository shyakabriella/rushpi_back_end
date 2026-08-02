<?php

declare(strict_types=1);

namespace App\Http\Resources;

use BackedEnum;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PublicProductResource extends JsonResource
{
    /**
     * Transform the product into customer-safe catalog data.
     *
     * This resource must never expose:
     * - internal database identifiers
     * - product cost prices
     * - seller private contact information
     * - product moderation notes
     * - stock reservation details
     * - stock movement history
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $variants = $this->loadedActiveVariants();
        $media = $this->loadedPublicMedia();

        return [
            'public_id' => (string) $this->public_id,

            'name' => (string) $this->name,

            'slug' => (string) $this->slug,

            'short_description' => $this->short_description,

            'description' => $this->description,

            'condition' => [
                'value' => $this->enumValue(
                    $this->condition
                ),

                'label' => $this->enumLabel(
                    $this->condition
                ),
            ],

            'warranty_months' => $this->warranty_months !== null
                ? (int) $this->warranty_months
                : null,

            'specifications' => is_array($this->specifications)
                ? $this->specifications
                : [],

            /*
            |--------------------------------------------------------------------------
            | Public category
            |--------------------------------------------------------------------------
            */

            'category' => $this->when(
                $this->relationLoaded('category')
                    && $this->category !== null,
                fn (): array => [
                    'public_id' => (string) $this->category->public_id,

                    'name' => (string) $this->category->name,

                    'slug' => (string) $this->category->slug,
                ]
            ),

            /*
            |--------------------------------------------------------------------------
            | Public brand
            |--------------------------------------------------------------------------
            */

            'brand' => $this->when(
                $this->relationLoaded('brand')
                    && $this->brand !== null,
                fn (): array => [
                    'public_id' => (string) $this->brand->public_id,

                    'name' => (string) $this->brand->name,

                    'slug' => (string) $this->brand->slug,

                    'logo_path' => $this->brand->logo_path,
                ]
            ),

            /*
            |--------------------------------------------------------------------------
            | Public seller information
            |--------------------------------------------------------------------------
            |
            | Email, telephone, address, registration numbers and internal
            | verification information must not be returned here.
            |
            */

            'seller' => $this->when(
                $this->relationLoaded('sellerProfile')
                    && $this->sellerProfile !== null,
                fn (): array => [
                    'public_id' => (string) $this->sellerProfile->public_id,

                    'legal_business_name' =>
                        $this->sellerProfile->legal_business_name,

                    'trading_name' =>
                        $this->sellerProfile->trading_name,
                ]
            ),

            /*
            |--------------------------------------------------------------------------
            | Product images
            |--------------------------------------------------------------------------
            */

            'primary_image' => $this->when(
                $this->relationLoaded('media'),
                fn (): ?array => $this->primaryImage($media)
            ),

            'media' => $this->when(
                $this->relationLoaded('media'),
                fn (): array => $media->values()->all()
            ),

            /*
            |--------------------------------------------------------------------------
            | Product pricing summary
            |--------------------------------------------------------------------------
            */

            'pricing' => $this->when(
                $this->variantsAreLoaded(),
                fn (): array => $this->priceSummary($variants)
            ),

            /*
            |--------------------------------------------------------------------------
            | Product inventory summary
            |--------------------------------------------------------------------------
            */

            'availability' => $this->when(
                $this->variantsAreLoaded(),
                fn (): array => $this->availabilitySummary($variants)
            ),

            /*
            |--------------------------------------------------------------------------
            | Active public variants
            |--------------------------------------------------------------------------
            */

            'variants' => $this->when(
                $this->variantsAreLoaded(),
                fn () => PublicProductVariantResource::collection(
                    $variants
                )
            ),

            'variants_count' => $this->when(
                $this->variantsAreLoaded(),
                fn (): int => $variants->count()
            ),

            /*
            |--------------------------------------------------------------------------
            | Public publication information
            |--------------------------------------------------------------------------
            */

            'published_at' => $this->approved_at?->toIso8601String(),

            'created_at' => $this->created_at?->toIso8601String(),

            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Determine whether a variant relationship was eager loaded.
     */
    private function variantsAreLoaded(): bool
    {
        return $this->relationLoaded('activeVariants')
            || $this->relationLoaded('variants');
    }

    /**
     * Return active variants that were already eager loaded.
     *
     * @return Collection<int, mixed>
     */
    private function loadedActiveVariants(): Collection
    {
        if ($this->relationLoaded('activeVariants')) {
            return $this->normalizeVariantCollection(
                $this->activeVariants
            );
        }

        if ($this->relationLoaded('variants')) {
            return $this->normalizeVariantCollection(
                $this->variants
            )
                ->filter(
                    static fn ($variant): bool =>
                        (bool) $variant->is_active
                )
                ->values();
        }

        return collect();
    }

    /**
     * Normalize and order a variant collection.
     *
     * @param Collection<int, mixed>|EloquentCollection<int, mixed> $variants
     *
     * @return Collection<int, mixed>
     */
    private function normalizeVariantCollection(
        Collection|EloquentCollection $variants
    ): Collection {
        return collect($variants)
            ->sortBy([
                ['is_default', 'desc'],
                ['sort_order', 'asc'],
                ['created_at', 'asc'],
            ])
            ->values();
    }

    /**
     * Return public product media.
     *
     * Product-level media and variant-level media may both be included.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function loadedPublicMedia(): Collection
    {
        if (!$this->relationLoaded('media')) {
            return collect();
        }

        return collect($this->media)
            ->sortBy([
                ['is_primary', 'desc'],
                ['sort_order', 'asc'],
                ['created_at', 'asc'],
            ])
            ->values()
            ->map(
                fn ($media): array => [
                    'public_id' => (string) $media->public_id,

                    'media_type' => $this->enumValue(
                        $media->media_type
                    ),

                    'url' => $media->url,

                    'alt_text' => $media->alt_text,

                    'is_primary' => (bool) $media->is_primary,

                    'sort_order' => (int) $media->sort_order,

                    'variant_public_id' =>
                        $media->relationLoaded('variant')
                        && $media->variant !== null
                            ? (string) $media->variant->public_id
                            : null,
                ]
            );
    }

    /**
     * Return the product's primary public image.
     *
     * @param Collection<int, array<string, mixed>> $media
     *
     * @return array<string, mixed>|null
     */
    private function primaryImage(Collection $media): ?array
    {
        $primary = $media->first(
            static fn (array $item): bool =>
                (bool) ($item['is_primary'] ?? false)
        );

        if (is_array($primary)) {
            return $primary;
        }

        $first = $media->first();

        return is_array($first)
            ? $first
            : null;
    }

    /**
     * Calculate the customer-safe product price range.
     *
     * Cost prices and profit information are intentionally excluded.
     *
     * @param Collection<int, mixed> $variants
     *
     * @return array<string, mixed>
     */
    private function priceSummary(Collection $variants): array
    {
        $prices = $variants
            ->filter(
                static fn ($variant): bool =>
                    $variant->relationLoaded('price')
                    && $variant->price !== null
                    && $variant->price->selling_price !== null
            )
            ->map(
                static fn ($variant): array => [
                    'currency' => strtoupper(
                        (string) (
                            $variant->price->currency ?? 'RWF'
                        )
                    ),

                    'selling_price' => (float) (
                        $variant->price->selling_price
                    ),

                    'compare_at_price' =>
                        $variant->price->compare_at_price !== null
                            ? (float) $variant->price->compare_at_price
                            : null,
                ]
            )
            ->values();

        if ($prices->isEmpty()) {
            return [
                'has_price' => false,

                'currency' => null,

                'minimum_price' => null,

                'maximum_price' => null,

                'formatted_minimum_price' => null,

                'formatted_maximum_price' => null,

                'has_price_range' => false,

                'has_discount' => false,
            ];
        }

        $currencies = $prices
            ->pluck('currency')
            ->filter()
            ->unique()
            ->values();

        $currency = $currencies->count() === 1
            ? (string) $currencies->first()
            : null;

        $minimumPrice = (float) $prices->min(
            'selling_price'
        );

        $maximumPrice = (float) $prices->max(
            'selling_price'
        );

        $hasDiscount = $prices->contains(
            static function (array $price): bool {
                $compareAtPrice = $price['compare_at_price'];

                return $compareAtPrice !== null
                    && $compareAtPrice > $price['selling_price'];
            }
        );

        return [
            'has_price' => true,

            'currency' => $currency,

            'minimum_price' => number_format(
                $minimumPrice,
                2,
                '.',
                ''
            ),

            'maximum_price' => number_format(
                $maximumPrice,
                2,
                '.',
                ''
            ),

            'formatted_minimum_price' => $currency !== null
                ? sprintf(
                    '%s %s',
                    $currency,
                    number_format($minimumPrice, 2)
                )
                : number_format($minimumPrice, 2),

            'formatted_maximum_price' => $currency !== null
                ? sprintf(
                    '%s %s',
                    $currency,
                    number_format($maximumPrice, 2)
                )
                : number_format($maximumPrice, 2),

            'has_price_range' => $minimumPrice !== $maximumPrice,

            'has_discount' => $hasDiscount,
        ];
    }

    /**
     * Calculate public stock availability across active variants.
     *
     * @param Collection<int, mixed> $variants
     *
     * @return array<string, mixed>
     */
    private function availabilitySummary(
        Collection $variants
    ): array {
        $inventoryRecords = $variants
            ->filter(
                static fn ($variant): bool =>
                    $variant->relationLoaded('inventoryStock')
                    && $variant->inventoryStock !== null
            )
            ->map(
                static function ($variant): array {
                    $quantityOnHand = (int) (
                        $variant
                            ->inventoryStock
                            ->quantity_on_hand ?? 0
                    );

                    $quantityReserved = (int) (
                        $variant
                            ->inventoryStock
                            ->quantity_reserved ?? 0
                    );

                    $reorderLevel = (int) (
                        $variant
                            ->inventoryStock
                            ->reorder_level ?? 0
                    );

                    $allowBackorder = (bool) (
                        $variant
                            ->inventoryStock
                            ->allow_backorder ?? false
                    );

                    $availableQuantity = max(
                        0,
                        $quantityOnHand - $quantityReserved
                    );

                    return [
                        'available_quantity' =>
                            $availableQuantity,

                        'reorder_level' =>
                            $reorderLevel,

                        'allow_backorder' =>
                            $allowBackorder,

                        'is_available' =>
                            $availableQuantity > 0
                            || $allowBackorder,

                        'is_low_stock' =>
                            $availableQuantity > 0
                            && $availableQuantity <= $reorderLevel,
                    ];
                }
            )
            ->values();

        if ($inventoryRecords->isEmpty()) {
            return [
                'is_available' => false,

                'stock_status' => 'out_of_stock',

                'available_variants_count' => 0,

                'total_variants_count' => $variants->count(),

                'total_available_quantity' => 0,
            ];
        }

        $availableVariantsCount = $inventoryRecords
            ->filter(
                static fn (array $inventory): bool =>
                    $inventory['is_available']
            )
            ->count();

        $totalAvailableQuantity = (int) $inventoryRecords
            ->sum('available_quantity');

        $hasLowStockVariant = $inventoryRecords
            ->contains(
                static fn (array $inventory): bool =>
                    $inventory['is_low_stock']
            );

        $stockStatus = match (true) {
            $availableVariantsCount === 0 =>
                'out_of_stock',

            $hasLowStockVariant =>
                'low_stock',

            default =>
                'in_stock',
        };

        return [
            'is_available' => $availableVariantsCount > 0,

            'stock_status' => $stockStatus,

            'available_variants_count' =>
                $availableVariantsCount,

            'total_variants_count' =>
                $variants->count(),

            'total_available_quantity' =>
                $totalAvailableQuantity,
        ];
    }

    /**
     * Return a backed enum value or plain string.
     */
    private function enumValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return (string) $value;
    }

    /**
     * Return an enum label when available.
     */
    private function enumLabel(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (
            is_object($value)
            && method_exists($value, 'label')
        ) {
            return (string) $value->label();
        }

        $enumValue = $this->enumValue($value);

        return $enumValue !== null
            ? Str::headline($enumValue)
            : null;
    }
}
