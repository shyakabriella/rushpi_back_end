<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProductCondition;
use App\Enums\ProductStatus;
use App\Enums\SellerProfileStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory;
    use HasUlids;
    use SoftDeletes;

    /**
     * Attributes that may be mass assigned.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'seller_profile_id',
        'category_id',
        'brand_id',
        'name',
        'slug',
        'short_description',
        'description',
        'condition',
        'warranty_months',
        'specifications',
        'status',
        'rejection_reason',
        'suspension_reason',
        'submitted_at',
        'approved_at',
        'rejected_at',
        'suspended_at',
        'archived_at',
        'created_by',
        'updated_by',
        'approved_by',
    ];

    /**
     * Attribute casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'seller_profile_id' => 'integer',

            'category_id' => 'integer',

            'brand_id' => 'integer',

            'condition' => ProductCondition::class,

            'warranty_months' => 'integer',

            'specifications' => 'array',

            'status' => ProductStatus::class,

            'submitted_at' => 'datetime',

            'approved_at' => 'datetime',

            'rejected_at' => 'datetime',

            'suspended_at' => 'datetime',

            'archived_at' => 'datetime',

            'created_at' => 'datetime',

            'updated_at' => 'datetime',

            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Generate a ULID for the public identifier.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return [
            'public_id',
        ];
    }

    /**
     * Use the public identifier for implicit route binding.
     */
    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * Normalize product values before persistence.
     */
    protected static function booted(): void
    {
        static::creating(
            static function (Product $product): void {
                if ($product->status === null) {
                    $product->status = ProductStatus::DRAFT;
                }
            }
        );

        static::saving(
            static function (Product $product): void {
                $product->name = trim(
                    (string) $product->name
                );

                if (
                    $product->slug === null
                    || trim((string) $product->slug) === ''
                ) {
                    $product->slug = Str::slug(
                        $product->name
                    );
                } else {
                    $product->slug = Str::slug(
                        trim((string) $product->slug)
                    );
                }

                if ($product->short_description !== null) {
                    $shortDescription = trim(
                        (string) $product->short_description
                    );

                    $product->short_description =
                        $shortDescription !== ''
                            ? $shortDescription
                            : null;
                }

                if ($product->description !== null) {
                    $description = trim(
                        (string) $product->description
                    );

                    $product->description =
                        $description !== ''
                            ? $description
                            : null;
                }

                if ($product->rejection_reason !== null) {
                    $rejectionReason = trim(
                        (string) $product->rejection_reason
                    );

                    $product->rejection_reason =
                        $rejectionReason !== ''
                            ? $rejectionReason
                            : null;
                }

                if ($product->suspension_reason !== null) {
                    $suspensionReason = trim(
                        (string) $product->suspension_reason
                    );

                    $product->suspension_reason =
                        $suspensionReason !== ''
                            ? $suspensionReason
                            : null;
                }

                if (!is_array($product->specifications)) {
                    $product->specifications = [];
                }
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Ownership and catalog relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Seller profile that owns this product.
     *
     * @return BelongsTo<SellerProfile, $this>
     */
    public function sellerProfile(): BelongsTo
    {
        return $this->belongsTo(
            SellerProfile::class,
            'seller_profile_id'
        );
    }

    /**
     * Product category.
     *
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(
            Category::class,
            'category_id'
        );
    }

    /**
     * Optional product brand.
     *
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(
            Brand::class,
            'brand_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Product variants
    |--------------------------------------------------------------------------
    */

    /**
     * All product variants.
     *
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(
            ProductVariant::class,
            'product_id'
        );
    }

    /**
     * Active product variants.
     *
     * @return HasMany<ProductVariant, $this>
     */
    public function activeVariants(): HasMany
    {
        return $this
            ->variants()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * Default active product variant.
     *
     * @return HasOne<ProductVariant, $this>
     */
    public function defaultVariant(): HasOne
    {
        return $this
            ->hasOne(
                ProductVariant::class,
                'product_id'
            )
            ->where('is_active', true)
            ->where('is_default', true);
    }

    /*
    |--------------------------------------------------------------------------
    | Product media
    |--------------------------------------------------------------------------
    */

    /**
     * Product media records.
     *
     * @return HasMany<ProductMedia, $this>
     */
    public function media(): HasMany
    {
        return $this
            ->hasMany(
                ProductMedia::class,
                'product_id'
            )
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * Primary product image.
     *
     * @return HasOne<ProductMedia, $this>
     */
    public function primaryMedia(): HasOne
    {
        return $this
            ->hasOne(
                ProductMedia::class,
                'product_id'
            )
            ->where('is_primary', true);
    }

    /*
    |--------------------------------------------------------------------------
    | Product moderation
    |--------------------------------------------------------------------------
    */

    /**
     * Product moderation history.
     *
     * @return HasMany<ProductModerationReview, $this>
     */
    public function moderationReviews(): HasMany
    {
        return $this
            ->hasMany(
                ProductModerationReview::class,
                'product_id'
            )
            ->latest('id');
    }

    /**
     * Latest product moderation review.
     *
     * @return HasOne<ProductModerationReview, $this>
     */
    public function latestModerationReview(): HasOne
    {
        return $this
            ->hasOne(
                ProductModerationReview::class,
                'product_id'
            )
            ->latestOfMany();
    }

    /*
    |--------------------------------------------------------------------------
    | Audit users
    |--------------------------------------------------------------------------
    */

    /**
     * User who created the product.
     *
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    /**
     * User who last updated the product.
     *
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'updated_by'
        );
    }

    /**
     * Administrator who approved the product.
     *
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'approved_by'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Query scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Restrict products to one seller profile.
     *
     * @param Builder<Product> $query
     *
     * @return Builder<Product>
     */
    public function scopeForSeller(
        Builder $query,
        int $sellerProfileId
    ): Builder {
        return $query->where(
            'seller_profile_id',
            $sellerProfileId
        );
    }

    /**
     * Restrict products to a status.
     *
     * @param Builder<Product> $query
     *
     * @return Builder<Product>
     */
    public function scopeWithStatus(
        Builder $query,
        ProductStatus|string $status
    ): Builder {
        $statusValue = $status instanceof ProductStatus
            ? $status->value
            : $status;

        return $query->where(
            'status',
            $statusValue
        );
    }

    /**
     * Restrict products to approved products.
     *
     * @param Builder<Product> $query
     *
     * @return Builder<Product>
     */
    public function scopeApproved(
        Builder $query
    ): Builder {
        return $query->where(
            'status',
            ProductStatus::APPROVED->value
        );
    }

    /**
     * Restrict products to pending-review products.
     *
     * @param Builder<Product> $query
     *
     * @return Builder<Product>
     */
    public function scopePendingReview(
        Builder $query
    ): Builder {
        return $query->where(
            'status',
            ProductStatus::PENDING_REVIEW->value
        );
    }

    /**
     * Restrict products to those allowed in the public catalog.
     *
     * @param Builder<Product> $query
     *
     * @return Builder<Product>
     */
    public function scopePubliclyVisible(
        Builder $query
    ): Builder {
        return $query
            ->where(
                'status',
                ProductStatus::APPROVED->value
            )
            ->whereHas(
                'sellerProfile',
                static function (
                    Builder $sellerQuery
                ): void {
                    $sellerQuery->where(
                        'status',
                        SellerProfileStatus::APPROVED->value
                    );
                }
            )
            ->whereHas(
                'category',
                static function (
                    Builder $categoryQuery
                ): void {
                    $categoryQuery->where(
                        'is_active',
                        true
                    );
                }
            )
            ->where(
                static function (
                    Builder $brandQuery
                ): void {
                    $brandQuery
                        ->whereNull('brand_id')
                        ->orWhereHas(
                            'brand',
                            static function (
                                Builder $query
                            ): void {
                                $query->where(
                                    'is_active',
                                    true
                                );
                            }
                        );
                }
            )
            ->whereHas(
                'activeVariants',
                static function (
                    Builder $variantQuery
                ): void {
                    $variantQuery->whereHas(
                        'price',
                        static function (
                            Builder $priceQuery
                        ): void {
                            $priceQuery->where(
                                'selling_price',
                                '>',
                                0
                            );
                        }
                    );
                }
            );
    }

    /**
     * Search products.
     *
     * @param Builder<Product> $query
     *
     * @return Builder<Product>
     */
    public function scopeSearch(
        Builder $query,
        ?string $search
    ): Builder {
        $search = trim(
            (string) $search
        );

        if ($search === '') {
            return $query;
        }

        $escapedSearch = addcslashes(
            $search,
            '\\%_'
        );

        $like = "%{$escapedSearch}%";

        return $query->where(
            static function (
                Builder $searchQuery
            ) use ($like): void {
                $searchQuery
                    ->where('name', 'like', $like)
                    ->orWhere('slug', 'like', $like)
                    ->orWhere(
                        'short_description',
                        'like',
                        $like
                    )
                    ->orWhere(
                        'description',
                        'like',
                        $like
                    )
                    ->orWhereHas(
                        'variants',
                        static function (
                            Builder $variantQuery
                        ) use ($like): void {
                            $variantQuery
                                ->where(
                                    'sku',
                                    'like',
                                    $like
                                )
                                ->orWhere(
                                    'barcode',
                                    'like',
                                    $like
                                )
                                ->orWhere(
                                    'name',
                                    'like',
                                    $like
                                );
                        }
                    );
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Lifecycle helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether the product has the supplied status.
     */
    public function hasStatus(
        ProductStatus $status
    ): bool {
        return $this->status === $status;
    }

    /**
     * Determine whether the product is a draft.
     */
    public function isDraft(): bool
    {
        return $this->hasStatus(
            ProductStatus::DRAFT
        );
    }

    /**
     * Determine whether the product is pending review.
     */
    public function isPendingReview(): bool
    {
        return $this->hasStatus(
            ProductStatus::PENDING_REVIEW
        );
    }

    /**
     * Determine whether the product is approved.
     */
    public function isApproved(): bool
    {
        return $this->hasStatus(
            ProductStatus::APPROVED
        );
    }

    /**
     * Determine whether the product is rejected.
     */
    public function isRejected(): bool
    {
        return $this->hasStatus(
            ProductStatus::REJECTED
        );
    }

    /**
     * Determine whether the product is suspended.
     */
    public function isSuspended(): bool
    {
        return $this->hasStatus(
            ProductStatus::SUSPENDED
        );
    }

    /**
     * Determine whether the product is archived.
     */
    public function isArchived(): bool
    {
        return $this->hasStatus(
            ProductStatus::ARCHIVED
        );
    }

    /**
     * Determine whether the seller may edit the product.
     */
    public function canBeEdited(): bool
    {
        return in_array(
            $this->status,
            [
                ProductStatus::DRAFT,
                ProductStatus::REJECTED,
                ProductStatus::APPROVED,
            ],
            true
        );
    }

    /**
     * Determine whether the product may be submitted for review.
     */
    public function canBeSubmittedForReview(): bool
    {
        return in_array(
            $this->status,
            [
                ProductStatus::DRAFT,
                ProductStatus::REJECTED,
            ],
            true
        );
    }

    /**
     * Determine whether the product may appear publicly.
     */
    public function isPubliclyVisible(): bool
    {
        if (!$this->isApproved()) {
            return false;
        }

        $seller = $this->relationLoaded(
            'sellerProfile'
        )
            ? $this->sellerProfile
            : $this->sellerProfile()->first();

        if (
            $seller === null
            || !$seller->isApproved()
        ) {
            return false;
        }

        $category = $this->relationLoaded('category')
            ? $this->category
            : $this->category()->first();

        if (
            $category === null
            || !$category->is_active
        ) {
            return false;
        }

        $brand = $this->relationLoaded('brand')
            ? $this->brand
            : $this->brand()->first();

        if (
            $brand !== null
            && !$brand->is_active
        ) {
            return false;
        }

        return $this
            ->activeVariants()
            ->whereHas(
                'price',
                static function (
                    Builder $query
                ): void {
                    $query->where(
                        'selling_price',
                        '>',
                        0
                    );
                }
            )
            ->exists();
    }

    /*
    |--------------------------------------------------------------------------
    | Specification values
    |--------------------------------------------------------------------------
    */

    /**
     * Return normalized product specification values.
     *
     * Specifications are stored using definition codes:
     *
     * [
     *     'processor' => 'Intel Core i7',
     *     'ram' => 16,
     *     'storage_capacity' => 512,
     *     'screen_size' => 15.6,
     * ]
     *
     * @return array<string, mixed>
     */
    public function specificationValues(): array
    {
        if (!is_array($this->specifications)) {
            return [];
        }

        return collect($this->specifications)
            ->mapWithKeys(
                static function (
                    mixed $value,
                    string|int $code
                ): array {
                    $normalizedCode = Str::snake(
                        trim((string) $code)
                    );

                    return [
                        $normalizedCode => $value,
                    ];
                }
            )
            ->all();
    }

    /**
     * Return one specification value.
     */
    public function specificationValue(
        string $code,
        mixed $default = null
    ): mixed {
        $normalizedCode = Str::snake(
            trim($code)
        );

        return $this->specificationValues()[
            $normalizedCode
        ] ?? $default;
    }

    /**
     * Determine whether a specification contains a meaningful value.
     */
    public function hasSpecificationValue(
        string $code
    ): bool {
        $normalizedCode = Str::snake(
            trim($code)
        );

        $values = $this->specificationValues();

        if (!array_key_exists($normalizedCode, $values)) {
            return false;
        }

        return $this->valueIsPresent(
            $values[$normalizedCode]
        );
    }

    /**
     * Add or replace one specification value.
     */
    public function setSpecificationValue(
        string $code,
        mixed $value
    ): self {
        $normalizedCode = Str::snake(
            trim($code)
        );

        $values = $this->specificationValues();

        $values[$normalizedCode] = $value;

        $this->specifications = $values;

        return $this;
    }

    /**
     * Remove one specification value.
     */
    public function removeSpecificationValue(
        string $code
    ): self {
        $normalizedCode = Str::snake(
            trim($code)
        );

        $values = $this->specificationValues();

        unset($values[$normalizedCode]);

        $this->specifications = $values;

        return $this;
    }

    /**
     * Determine whether a submitted value is present.
     */
    private function valueIsPresent(
        mixed $value
    ): bool {
        if ($value === null) {
            return false;
        }

        if (
            is_string($value)
            && trim($value) === ''
        ) {
            return false;
        }

        if (
            is_array($value)
            && $value === []
        ) {
            return false;
        }

        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | Category specification helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Return the product category model.
     */
    private function productCategory(): ?Category
    {
        if ($this->relationLoaded('category')) {
            return $this->category;
        }

        return $this
            ->category()
            ->first();
    }

    /**
     * Return effective category specifications.
     *
     * Parent category specifications are inherited.
     *
     * @return SupportCollection<int, CategorySpecification>
     */
    public function categorySpecifications(): SupportCollection
    {
        $category = $this->productCategory();

        if ($category === null) {
            return collect();
        }

        return $category
            ->effectiveSpecificationAssignments();
    }

    /**
     * Return effective required category specifications.
     *
     * @return SupportCollection<int, CategorySpecification>
     */
    public function requiredCategorySpecifications(): SupportCollection
    {
        return $this
            ->categorySpecifications()
            ->filter(
                static fn (
                    CategorySpecification $assignment
                ): bool =>
                    $assignment->isRequired()
            )
            ->values();
    }

    /**
     * Return required specifications missing from the product.
     *
     * @return SupportCollection<int, CategorySpecification>
     */
    public function missingRequiredSpecifications(): SupportCollection
    {
        return $this
            ->requiredCategorySpecifications()
            ->filter(
                fn (
                    CategorySpecification $assignment
                ): bool =>
                    !$this->hasSpecificationValue(
                        $assignment->code()
                    )
            )
            ->values();
    }

    /**
     * Return missing required specification codes.
     *
     * @return array<int, string>
     */
    public function missingRequiredSpecificationCodes(): array
    {
        return $this
            ->missingRequiredSpecifications()
            ->map(
                static fn (
                    CategorySpecification $assignment
                ): string =>
                    $assignment->code()
            )
            ->values()
            ->all();
    }

    /**
     * Return specification codes that are not assigned to the category.
     *
     * @return array<int, string>
     */
    public function unknownSpecificationCodes(): array
    {
        $allowedCodes = $this
            ->categorySpecifications()
            ->map(
                static fn (
                    CategorySpecification $assignment
                ): string =>
                    $assignment->code()
            )
            ->values();

        return collect(
            array_keys(
                $this->specificationValues()
            )
        )
            ->diff($allowedCodes)
            ->values()
            ->all();
    }

    /**
     * Determine whether all required specifications are present.
     */
    public function hasRequiredSpecifications(): bool
    {
        return $this
            ->missingRequiredSpecifications()
            ->isEmpty();
    }

    /*
    |--------------------------------------------------------------------------
    | Submission and publication readiness
    |--------------------------------------------------------------------------
    */

    /**
     * Return specification-related readiness errors.
     *
     * Typed validation is added later by the specification validator service.
     *
     * @return array<int, string>
     */
    public function specificationReadinessErrors(): array
    {
        $errors = [];

        foreach (
            $this->missingRequiredSpecifications()
            as $assignment
        ) {
            $errors[] = sprintf(
                'The %s specification is required.',
                $assignment->effectiveLabel()
            );
        }

        return $errors;
    }

    /**
     * Return all product submission-readiness errors.
     *
     * @return array<int, string>
     */
    public function publicationReadinessErrors(): array
    {
        $errors = [];

        if (
            trim((string) $this->name) === ''
        ) {
            $errors[] = 'The product name is required.';
        }

        if (
            $this->short_description === null
            && $this->description === null
        ) {
            $errors[] =
                'A product description is required.';
        }

        $seller = $this->relationLoaded(
            'sellerProfile'
        )
            ? $this->sellerProfile
            : $this->sellerProfile()->first();

        if ($seller === null) {
            $errors[] =
                'The product must belong to a seller profile.';
        } elseif (!$seller->isApproved()) {
            $errors[] =
                'The seller profile must be approved.';
        }

        $category = $this->productCategory();

        if ($category === null) {
            $errors[] =
                'The product category is required.';
        } elseif (!$category->is_active) {
            $errors[] =
                'The product category must be active.';
        }

        $brand = $this->relationLoaded('brand')
            ? $this->brand
            : $this->brand()->first();

        if (
            $brand !== null
            && !$brand->is_active
        ) {
            $errors[] =
                'The selected product brand is inactive.';
        }

        $errors = array_merge(
            $errors,
            $this->specificationReadinessErrors()
        );

        $variants = $this->activeVariantRecords();

        if ($variants->isEmpty()) {
            $errors[] =
                'At least one active product variant is required.';
        }

        foreach ($variants as $variant) {
            if (
                trim((string) $variant->sku) === ''
            ) {
                $errors[] = sprintf(
                    'Variant %s requires a SKU.',
                    $variant->public_id
                        ?? $variant->getKey()
                );
            }

            if (
                !$variant->relationLoaded('price')
            ) {
                $variant->load('price');
            }

            if (
                $variant->price === null
                || (float) $variant->price->selling_price <= 0
            ) {
                $errors[] = sprintf(
                    'Variant %s requires a valid selling price.',
                    $variant->sku
                        ?: $variant->public_id
                );
            }

            if (
                !$variant->relationLoaded(
                    'inventoryStock'
                )
            ) {
                $variant->load('inventoryStock');
            }

            if ($variant->inventoryStock === null) {
                $errors[] = sprintf(
                    'Variant %s requires an inventory record.',
                    $variant->sku
                        ?: $variant->public_id
                );
            }
        }

        if ($this->productMediaRecords()->isEmpty()) {
            $errors[] =
                'At least one product image is required.';
        }

        return array_values(
            array_unique($errors)
        );
    }

    /**
     * Determine whether the product is ready for moderation submission.
     */
    public function isReadyForSubmission(): bool
    {
        return $this
            ->publicationReadinessErrors() === [];
    }

    /**
     * Return a structured readiness summary.
     *
     * @return array<string, mixed>
     */
    public function publicationReadiness(): array
    {
        $errors = $this
            ->publicationReadinessErrors();

        return [
            'is_ready' => $errors === [],

            'errors' => $errors,

            'missing_required_specifications' =>
                $this
                    ->missingRequiredSpecifications()
                    ->map(
                        static fn (
                            CategorySpecification $assignment
                        ): array => [
                            'public_id' =>
                                (string) $assignment->public_id,

                            'code' =>
                                $assignment->code(),

                            'label' =>
                                $assignment->effectiveLabel(),

                            'data_type' =>
                                $assignment
                                    ->dataType()
                                    ->value,

                            'unit' =>
                                $assignment->unit(),
                        ]
                    )
                    ->values()
                    ->all(),
        ];
    }

    /**
     * Return active variants with pricing and inventory loaded.
     *
     * @return Collection<int, ProductVariant>
     */
    private function activeVariantRecords(): Collection
    {
        if (
            $this->relationLoaded(
                'activeVariants'
            )
        ) {
            $variants = $this->activeVariants;

            $variants->loadMissing([
                'price',
                'inventoryStock',
            ]);

            return $variants;
        }

        if ($this->relationLoaded('variants')) {
            $variants = $this
                ->variants
                ->filter(
                    static fn (
                        ProductVariant $variant
                    ): bool =>
                        (bool) $variant->is_active
                )
                ->values();

            $variants->loadMissing([
                'price',
                'inventoryStock',
            ]);

            return new Collection(
                $variants->all()
            );
        }

        return $this
            ->activeVariants()
            ->with([
                'price',
                'inventoryStock',
            ])
            ->get();
    }

    /**
     * Return product media records.
     *
     * @return Collection<int, ProductMedia>
     */
    private function productMediaRecords(): Collection
    {
        if ($this->relationLoaded('media')) {
            return $this->media;
        }

        return $this
            ->media()
            ->get();
    }
}