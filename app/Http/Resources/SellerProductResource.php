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
final class SellerProductResource extends JsonResource
{
    /**
     * Transform the seller product into an API response.
     *
     * @return array<string, mixed>
     */
    public function toArray(
        Request $request
    ): array {
        $readinessErrors =
            $this->publicationReadinessErrors();

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

            'status' =>
                $this->enumValue(
                    $this->status
                ),

            'warranty_months' =>
                $this->warranty_months !== null
                    ? (int) $this->warranty_months
                    : null,

            /*
            |--------------------------------------------------------------------------
            | Specifications
            |--------------------------------------------------------------------------
            */

            'specifications' =>
                $this->specificationValues(),

            'specification_readiness' => [
                'missing_required' =>
                    $this
                        ->missingRequiredSpecifications(),

                'errors' =>
                    $this
                        ->specificationReadinessErrors(),
            ],

            /*
            |--------------------------------------------------------------------------
            | Seller
            |--------------------------------------------------------------------------
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

                        return [
                            'public_id' =>
                                (string) $seller
                                    ->getAttribute(
                                        'public_id'
                                    ),

                            'legal_business_name' =>
                                $seller->getAttribute(
                                    'legal_business_name'
                                ),

                            'trading_name' =>
                                $seller->getAttribute(
                                    'trading_name'
                                ),

                            'status' =>
                                $this->enumValue(
                                    $seller->getAttribute(
                                        'status'
                                    )
                                ),
                        ];
                    }
                ),

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

                            'parent_id' =>
                                $category->getAttribute(
                                    'parent_id'
                                ) !== null
                                    ? (int) $category
                                        ->getAttribute(
                                            'parent_id'
                                        )
                                    : null,

                            'is_active' =>
                                (bool) $category
                                    ->getAttribute(
                                        'is_active'
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
                        $brand = $this->brand;

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

                            'is_active' =>
                                (bool) $brand
                                    ->getAttribute(
                                        'is_active'
                                    ),
                        ];
                    }
                ),

            /*
            |--------------------------------------------------------------------------
            | Return policy
            |--------------------------------------------------------------------------
            */

            'return_policy' =>
                $this->whenLoaded(
                    'returnPolicy',
                    function () use (
                        $request
                    ): ?array {
                        $policy =
                            $this->returnPolicy;

                        if (
                            !$policy instanceof
                            ProductReturnPolicy
                        ) {
                            return null;
                        }

                        return (
                            new ProductReturnPolicyResource(
                                $policy
                            )
                        )->resolve($request);
                    }
                ),

            'return_policy_readiness' => [
                'has_policy' =>
                    $this->returnPolicyExists(),

                'has_active_policy' =>
                    $this->hasActiveReturnPolicy(),

                'is_valid' =>
                    $this->hasValidReturnPolicy(),

                'errors' =>
                    $this
                        ->returnPolicyReadinessErrors(),
            ],

            /*
            |--------------------------------------------------------------------------
            | Product variants
            |--------------------------------------------------------------------------
            */

            'variants' =>
                $this->whenLoaded(
                    'variants',
                    function (): array {
                        return $this
                            ->variants
                            ->map(
                                fn (
                                    Model $variant
                                ): array =>
                                    $this
                                        ->variantData(
                                            $variant
                                        )
                            )
                            ->values()
                            ->all();
                    }
                ),

            /*
            |--------------------------------------------------------------------------
            | Product media
            |--------------------------------------------------------------------------
            */

            'media' =>
                $this->whenLoaded(
                    'media',
                    function (): array {
                        return $this
                            ->media
                            ->map(
                                fn (
                                    Model $media
                                ): array =>
                                    $this
                                        ->mediaData(
                                            $media
                                        )
                            )
                            ->values()
                            ->all();
                    }
                ),

            'primary_media' =>
                $this->whenLoaded(
                    'media',
                    function (): ?array {
                        $primaryMedia =
                            $this->media
                                ->first(
                                    static fn (
                                        Model $media
                                    ): bool =>
                                        (bool) $media
                                            ->getAttribute(
                                                'is_primary'
                                            )
                                )
                            ?? $this->media->first();

                        return $primaryMedia
                            instanceof Model
                                ? $this->mediaData(
                                    $primaryMedia
                                )
                                : null;
                    }
                ),

            /*
            |--------------------------------------------------------------------------
            | Counts and availability
            |--------------------------------------------------------------------------
            */

            'counts' => [
                'variants' =>
                    $this->relationCount(
                        relation:
                            'variants',

                        countAttribute:
                            'variants_count'
                    ),

                'media' =>
                    $this->relationCount(
                        relation:
                            'media',

                        countAttribute:
                            'media_count'
                    ),

                'moderation_reviews' =>
                    $this->relationCount(
                        relation:
                            'moderationReviews',

                        countAttribute:
                            'moderation_reviews_count'
                    ),
            ],

            'availability' => [
                'has_active_variant' =>
                    $this->loadedVariants()
                        ->contains(
                            static fn (
                                Model $variant
                            ): bool =>
                                (bool) $variant
                                    ->getAttribute(
                                        'is_active'
                                    )
                        ),

                'has_sellable_variant' =>
                    $this
                        ->loadedHasSellableVariant(),

                'has_inventory' =>
                    $this
                        ->loadedHasInventory(),

                'has_available_stock' =>
                    $this
                        ->loadedHasAvailableStock(),
            ],

            /*
            |--------------------------------------------------------------------------
            | Publication readiness
            |--------------------------------------------------------------------------
            */

            'publication_readiness' => [
                'is_ready' =>
                    $readinessErrors === [],

                'can_submit' =>
                    $this
                        ->canBeSubmittedForReview()
                    && $readinessErrors === [],

                'errors' =>
                    $readinessErrors,
            ],

            /*
            |--------------------------------------------------------------------------
            | Allowed actions
            |--------------------------------------------------------------------------
            */

            'actions' => [
                'can_edit' =>
                    $this->canBeEdited(),

                'can_submit_for_review' =>
                    $this
                        ->canBeSubmittedForReview()
                    && $readinessErrors === [],

                'can_manage_variants' =>
                    $this->canBeEdited(),

                'can_manage_pricing' =>
                    $this->canBeEdited(),

                'can_manage_inventory' =>
                    !$this->isArchived(),

                'can_manage_media' =>
                    $this->canBeEdited(),

                'can_manage_return_policy' =>
                    $this->canBeEdited(),

                'can_archive' =>
                    !$this->isPendingReview()
                    && !$this->isArchived(),
            ],

            /*
            |--------------------------------------------------------------------------
            | Moderation
            |--------------------------------------------------------------------------
            */

            'moderation' => [
                'submitted_at' =>
                    $this->dateValue(
                        $this->getAttribute(
                            'submitted_at'
                        )
                    ),

                'approved_at' =>
                    $this->dateValue(
                        $this->getAttribute(
                            'approved_at'
                        )
                    ),

                'rejected_at' =>
                    $this->dateValue(
                        $this->getAttribute(
                            'rejected_at'
                        )
                    ),

                'rejection_reason' =>
                    $this->getAttribute(
                        'rejection_reason'
                    ),

                'suspended_at' =>
                    $this->dateValue(
                        $this->getAttribute(
                            'suspended_at'
                        )
                    ),

                'suspension_reason' =>
                    $this->getAttribute(
                        'suspension_reason'
                    ),

                'archived_at' =>
                    $this->dateValue(
                        $this->getAttribute(
                            'archived_at'
                        )
                    ),

                'latest_review' =>
                    $this->whenLoaded(
                        'moderationReviews',
                        function (): ?array {
                            $review =
                                $this
                                    ->moderationReviews
                                    ->first();

                            return $review
                                instanceof Model
                                    ? $this
                                        ->moderationReviewData(
                                            $review
                                        )
                                    : null;
                        }
                    ),

                'history' =>
                    $this->whenLoaded(
                        'moderationReviews',
                        function (): array {
                            return $this
                                ->moderationReviews
                                ->map(
                                    fn (
                                        Model $review
                                    ): array =>
                                        $this
                                            ->moderationReviewData(
                                                $review
                                            )
                                )
                                ->values()
                                ->all();
                        }
                    ),
            ],

            /*
            |--------------------------------------------------------------------------
            | Timestamps
            |--------------------------------------------------------------------------
            */

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
     * Transform one product variant.
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

        $availableQuantity = null;

        if ($inventory instanceof Model) {
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
        }

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

            'barcode' =>
                $variant->getAttribute(
                    'barcode'
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

            'is_active' =>
                (bool) $variant
                    ->getAttribute(
                        'is_active'
                    ),

            'sort_order' =>
                (int) (
                    $variant->getAttribute(
                        'sort_order'
                    ) ?? 0
                ),

            'price' =>
                $price instanceof Model
                    ? $this->priceData(
                        $price
                    )
                    : null,

            'inventory' =>
                $inventory instanceof Model
                    ? $this->inventoryData(
                        $inventory
                    )
                    : null,

            'available_quantity' =>
                $availableQuantity,

            'is_in_stock' =>
                $inventory instanceof Model
                    ? (
                        $availableQuantity > 0
                        || (bool) $inventory
                            ->getAttribute(
                                'allow_backorder'
                            )
                    )
                    : false,

            'media' =>
                $variant
                    ->relationLoaded('media')
                    ? $variant
                        ->getRelation('media')
                        ->map(
                            fn (
                                Model $media
                            ): array =>
                                $this->mediaData(
                                    $media
                                )
                        )
                        ->values()
                        ->all()
                    : [],

            'created_at' =>
                $this->dateValue(
                    $variant->getAttribute(
                        'created_at'
                    )
                ),

            'updated_at' =>
                $this->dateValue(
                    $variant->getAttribute(
                        'updated_at'
                    )
                ),
        ];
    }

    /**
     * Transform variant pricing.
     *
     * @return array<string, mixed>
     */
    private function priceData(
        Model $price
    ): array {
        $currency =
            $price->getAttribute(
                'currency'
            )
            ?? $price->getAttribute(
                'currency_code'
            )
            ?? 'RWF';

        return [
            'public_id' =>
                (string) (
                    $price->getAttribute(
                        'public_id'
                    ) ?? ''
                ),

            'currency' =>
                (string) $currency,

            'cost_price' =>
                $this->numericValue(
                    $price->getAttribute(
                        'cost_price'
                    )
                ),

            'selling_price' =>
                $this->numericValue(
                    $price->getAttribute(
                        'selling_price'
                    )
                ),

            'compare_at_price' =>
                $this->numericValue(
                    $price->getAttribute(
                        'compare_at_price'
                    )
                ),

            'effective_from' =>
                $this->dateValue(
                    $price->getAttribute(
                        'effective_from'
                    )
                    ?? $price->getAttribute(
                        'starts_at'
                    )
                ),

            'effective_to' =>
                $this->dateValue(
                    $price->getAttribute(
                        'effective_to'
                    )
                    ?? $price->getAttribute(
                        'ends_at'
                    )
                ),

            'is_active' =>
                $price->getAttribute(
                    'is_active'
                ) !== null
                    ? (bool) $price
                        ->getAttribute(
                            'is_active'
                        )
                    : true,
        ];
    }

    /**
     * Transform variant inventory.
     *
     * @return array<string, mixed>
     */
    private function inventoryData(
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

        return [
            'public_id' =>
                (string) (
                    $inventory->getAttribute(
                        'public_id'
                    ) ?? ''
                ),

            'quantity_on_hand' =>
                $quantityOnHand,

            'quantity_reserved' =>
                $quantityReserved,

            'available_quantity' =>
                $availableQuantity,

            'reorder_level' =>
                (int) (
                    $inventory->getAttribute(
                        'reorder_level'
                    )
                    ?? $inventory->getAttribute(
                        'low_stock_threshold'
                    )
                    ?? 0
                ),

            'low_stock_threshold' =>
                (int) (
                    $inventory->getAttribute(
                        'low_stock_threshold'
                    )
                    ?? $inventory->getAttribute(
                        'reorder_level'
                    )
                    ?? 0
                ),

            'track_inventory' =>
                $inventory->getAttribute(
                    'track_inventory'
                ) !== null
                    ? (bool) $inventory
                        ->getAttribute(
                            'track_inventory'
                        )
                    : true,

            'allow_backorder' =>
                (bool) $inventory
                    ->getAttribute(
                        'allow_backorder'
                    ),

            'is_in_stock' =>
                $availableQuantity > 0
                || (bool) $inventory
                    ->getAttribute(
                        'allow_backorder'
                    ),

            'is_low_stock' =>
                $availableQuantity
                    <= (int) (
                        $inventory->getAttribute(
                            'low_stock_threshold'
                        )
                        ?? $inventory->getAttribute(
                            'reorder_level'
                        )
                        ?? 0
                    ),

            'created_at' =>
                $this->dateValue(
                    $inventory->getAttribute(
                        'created_at'
                    )
                ),

            'updated_at' =>
                $this->dateValue(
                    $inventory->getAttribute(
                        'updated_at'
                    )
                ),
        ];
    }

    /**
     * Transform one product media record.
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

        return [
            'public_id' =>
                (string) $media
                    ->getAttribute(
                        'public_id'
                    ),

            'variant_public_id' =>
                $media
                    ->relationLoaded('variant')
                && $media->getRelation(
                    'variant'
                ) instanceof Model
                    ? (string) $media
                        ->getRelation(
                            'variant'
                        )
                        ->getAttribute(
                            'public_id'
                        )
                    : null,

            'media_type' =>
                $this->enumValue(
                    $media->getAttribute(
                        'media_type'
                    )
                ),

            'disk' =>
                (string) $disk,

            'path' =>
                $path,

            'url' =>
                $this->mediaUrl(
                    $media,
                    (string) $disk,
                    is_string($path)
                        ? $path
                        : null
                ),

            'original_name' =>
                $media->getAttribute(
                    'original_name'
                ),

            'mime_type' =>
                $media->getAttribute(
                    'mime_type'
                ),

            'size_bytes' =>
                $media->getAttribute(
                    'size_bytes'
                ) !== null
                    ? (int) $media
                        ->getAttribute(
                            'size_bytes'
                        )
                    : null,

            'alt_text' =>
                $media->getAttribute(
                    'alt_text'
                ),

            'metadata' =>
                $media->getAttribute(
                    'metadata'
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

            'created_at' =>
                $this->dateValue(
                    $media->getAttribute(
                        'created_at'
                    )
                ),

            'updated_at' =>
                $this->dateValue(
                    $media->getAttribute(
                        'updated_at'
                    )
                ),
        ];
    }

    /**
     * Transform one moderation review.
     *
     * @return array<string, mixed>
     */
    private function moderationReviewData(
        Model $review
    ): array {
        $reviewer = $review
            ->relationLoaded('reviewer')
                ? $review->getRelation(
                    'reviewer'
                )
                : null;

        return [
            'public_id' =>
                (string) (
                    $review->getAttribute(
                        'public_id'
                    ) ?? ''
                ),

            'action' =>
                $this->enumValue(
                    $review->getAttribute(
                        'action'
                    )
                ),

            'previous_status' =>
                $this->enumValue(
                    $review->getAttribute(
                        'previous_status'
                    )
                ),

            'new_status' =>
                $this->enumValue(
                    $review->getAttribute(
                        'new_status'
                    )
                ),

            'notes' =>
                $review->getAttribute(
                    'notes'
                ),

            'reason' =>
                $review->getAttribute(
                    'reason'
                )
                ?? $review->getAttribute(
                    'rejection_reason'
                ),

            'reviewer' =>
                $reviewer instanceof Model
                    ? [
                        'public_id' =>
                            (string) (
                                $reviewer
                                    ->getAttribute(
                                        'public_id'
                                    ) ?? ''
                            ),

                        'name' =>
                            $reviewer->getAttribute(
                                'name'
                            ),

                        'email' =>
                            $reviewer->getAttribute(
                                'email'
                            ),
                    ]
                    : null,

            'created_at' =>
                $this->dateValue(
                    $review->getAttribute(
                        'created_at'
                    )
                ),
        ];
    }

    /**
     * Determine whether a return-policy record exists.
     */
    private function returnPolicyExists(): bool
    {
        if (
            $this->resource
                ->relationLoaded(
                    'returnPolicy'
                )
        ) {
            return $this->resource
                ->getRelation(
                    'returnPolicy'
                ) instanceof ProductReturnPolicy;
        }

        return $this->resource
            ->returnPolicy()
            ->exists();
    }

    /**
     * Return loaded variants without starting another query.
     *
     * @return Collection<int, Model>
     */
    private function loadedVariants(): Collection
    {
        if (
            !$this->resource
                ->relationLoaded('variants')
        ) {
            return collect();
        }

        $variants = $this->resource
            ->getRelation('variants');

        return $variants instanceof Collection
            ? $variants
            : collect();
    }

    /**
     * Determine whether loaded variants contain positive pricing.
     */
    private function loadedHasSellableVariant(): bool
    {
        return $this->loadedVariants()
            ->contains(
                static function (
                    Model $variant
                ): bool {
                    if (
                        !(bool) $variant
                            ->getAttribute(
                                'is_active'
                            )
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

                    $price = $variant
                        ->getRelation('price');

                    return $price instanceof Model
                        && (float) $price
                            ->getAttribute(
                                'selling_price'
                            ) > 0;
                }
            );
    }

    /**
     * Determine whether loaded variants contain inventory configuration.
     */
    private function loadedHasInventory(): bool
    {
        return $this->loadedVariants()
            ->contains(
                static function (
                    Model $variant
                ): bool {
                    return (bool) $variant
                        ->getAttribute(
                            'is_active'
                        )
                        && $variant
                            ->relationLoaded(
                                'inventoryStock'
                            )
                        && $variant
                            ->getRelation(
                                'inventoryStock'
                            ) instanceof Model;
                }
            );
    }

    /**
     * Determine whether loaded variants have stock or allow backorders.
     */
    private function loadedHasAvailableStock(): bool
    {
        return $this->loadedVariants()
            ->contains(
                static function (
                    Model $variant
                ): bool {
                    if (
                        !(bool) $variant
                            ->getAttribute(
                                'is_active'
                            )
                        || !$variant
                            ->relationLoaded(
                                'inventoryStock'
                            )
                    ) {
                        return false;
                    }

                    $inventory = $variant
                        ->getRelation(
                            'inventoryStock'
                        );

                    if (!$inventory instanceof Model) {
                        return false;
                    }

                    $quantityOnHand = (int) (
                        $inventory
                            ->getAttribute(
                                'quantity_on_hand'
                            ) ?? 0
                    );

                    $quantityReserved = (int) (
                        $inventory
                            ->getAttribute(
                                'quantity_reserved'
                            ) ?? 0
                    );

                    return $quantityOnHand
                        > $quantityReserved
                        || (bool) $inventory
                            ->getAttribute(
                                'allow_backorder'
                            );
                }
            );
    }

    /**
     * Return a relationship count without unnecessary queries.
     */
    private function relationCount(
        string $relation,
        string $countAttribute
    ): int {
        $storedCount = $this->resource
            ->getAttribute(
                $countAttribute
            );

        if ($storedCount !== null) {
            return (int) $storedCount;
        }

        if (
            !$this->resource
                ->relationLoaded($relation)
        ) {
            return 0;
        }

        $related = $this->resource
            ->getRelation($relation);

        if ($related instanceof Collection) {
            return $related->count();
        }

        return $related instanceof Model
            ? 1
            : 0;
    }

    /**
     * Return a media URL without failing the product response.
     */
    private function mediaUrl(
        Model $media,
        string $disk,
        ?string $path
    ): ?string {
        $existingUrl = $media->getAttribute(
            'url'
        );

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
     * Convert an enum or scalar into a string.
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
     * Convert a number-like value into a float.
     */
    private function numericValue(
        mixed $value
    ): ?float {
        return is_numeric($value)
            ? (float) $value
            : null;
    }

    /**
     * Convert a date value into an ISO-8601 string.
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