<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProductCondition;
use App\Enums\ProductStatus;
use App\Enums\SellerProfileStatus;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

final class Product extends Model
{
    use HasFactory;
    use HasUlids;

    /**
     * Product fields that may be mass assigned.
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
        'status',
        'specifications',
        'warranty_months',
        'submitted_at',
        'approved_at',
        'approved_by',
        'rejected_at',
        'rejected_by',
        'rejection_reason',
        'suspended_at',
        'suspended_by',
        'suspension_reason',
        'archived_at',
    ];

    /**
     * Model attribute casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'condition' =>
                ProductCondition::class,

            'status' =>
                ProductStatus::class,

            'specifications' =>
                'array',

            'warranty_months' =>
                'integer',

            'submitted_at' =>
                'datetime',

            'approved_at' =>
                'datetime',

            'rejected_at' =>
                'datetime',

            'suspended_at' =>
                'datetime',

            'archived_at' =>
                'datetime',

            'created_at' =>
                'datetime',

            'updated_at' =>
                'datetime',
        ];
    }

    /**
     * Generate a ULID for public_id while keeping the numeric primary key.
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
     * Use the public identifier for route-model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * Normalize product values before saving.
     */
    protected static function booted(): void
    {
        static::creating(
            static function (Product $product): void {
                if ($product->status === null) {
                    $product->status =
                        ProductStatus::DRAFT;
                }
            }
        );

        static::saving(
            static function (Product $product): void {
                $product->name = trim(
                    (string) $product->name
                );

                if (
                    trim((string) $product->slug) === ''
                    && $product->name !== ''
                ) {
                    $product->slug = Str::slug(
                        $product->name
                    );
                } else {
                    $product->slug = Str::slug(
                        (string) $product->slug
                    );
                }

                $product->short_description =
                    self::nullableTrim(
                        $product->short_description
                    );

                $product->description =
                    self::nullableTrim(
                        $product->description
                    );

                $product->rejection_reason =
                    self::nullableTrim(
                        $product->rejection_reason
                    );

                $product->suspension_reason =
                    self::nullableTrim(
                        $product->suspension_reason
                    );

                $product->specifications =
                    self::normalizeSpecificationValues(
                        $product->specifications
                    );

                if (
                    $product->warranty_months !== null
                    && (int) $product->warranty_months < 0
                ) {
                    $product->warranty_months = 0;
                }
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Core catalog relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Seller business that owns the product.
     */
    public function sellerProfile(): BelongsTo
    {
        return $this->belongsTo(
            SellerProfile::class
        );
    }

    /**
     * Product category.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(
            Category::class
        );
    }

    /**
     * Optional product brand.
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(
            Brand::class
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Variant relationships
    |--------------------------------------------------------------------------
    */

    /**
     * All product variants.
     */
    public function variants(): HasMany
    {
        return $this->hasMany(
            ProductVariant::class
        );
    }

    /**
     * Active product variants.
     */
    public function activeVariants(): HasMany
    {
        return $this->hasMany(
            ProductVariant::class
        )->where(
            'is_active',
            true
        );
    }

    /**
     * Default active product variant.
     */
    public function defaultVariant(): HasOne
    {
        return $this->hasOne(
            ProductVariant::class
        )
            ->where(
                'is_active',
                true
            )
            ->where(
                'is_default',
                true
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Media relationships
    |--------------------------------------------------------------------------
    */

    /**
     * All media attached to the product.
     */
    public function media(): HasMany
    {
        return $this->hasMany(
            ProductMedia::class
        );
    }

    /**
     * Primary product media.
     */
    public function primaryMedia(): HasOne
    {
        return $this->hasOne(
            ProductMedia::class
        )
            ->where(
                'is_primary',
                true
            )
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /*
    |--------------------------------------------------------------------------
    | Moderation relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Complete moderation history.
     */
    public function moderationReviews(): HasMany
    {
        return $this->hasMany(
            ProductModerationReview::class
        );
    }

    /**
     * Latest moderation review.
     */
    public function latestModerationReview(): HasOne
    {
        return $this->hasOne(
            ProductModerationReview::class
        )->latestOfMany();
    }

    /**
     * Administrator who approved the product.
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'approved_by'
        );
    }

    /**
     * Administrator who rejected the product.
     */
    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'rejected_by'
        );
    }

    /**
     * Administrator who suspended the product.
     */
    public function suspendedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'suspended_by'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Return-policy relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Return policy configured for this product.
     */
    public function returnPolicy(): HasOne
    {
        return $this->hasOne(
            ProductReturnPolicy::class
        );
    }

    /**
     * Active return policy configured for this product.
     */
    public function activeReturnPolicy(): HasOne
    {
        return $this->hasOne(
            ProductReturnPolicy::class
        )->where(
            'is_active',
            true
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Query scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Limit products to one seller profile.
     */
    public function scopeForSeller(
        Builder $query,
        SellerProfile|int $seller
    ): Builder {
        $sellerId = $seller instanceof SellerProfile
            ? $seller->getKey()
            : $seller;

        return $query->where(
            'seller_profile_id',
            $sellerId
        );
    }

    /**
     * Limit products to one category.
     */
    public function scopeForCategory(
        Builder $query,
        Category|int $category
    ): Builder {
        $categoryId = $category instanceof Category
            ? $category->getKey()
            : $category;

        return $query->where(
            'category_id',
            $categoryId
        );
    }

    /**
     * Limit products to one brand.
     */
    public function scopeForBrand(
        Builder $query,
        Brand|int $brand
    ): Builder {
        $brandId = $brand instanceof Brand
            ? $brand->getKey()
            : $brand;

        return $query->where(
            'brand_id',
            $brandId
        );
    }

    /**
     * Filter products by lifecycle status.
     */
    public function scopeStatus(
        Builder $query,
        ProductStatus|string $status
    ): Builder {
        $value = $status instanceof ProductStatus
            ? $status->value
            : $status;

        return $query->where(
            'status',
            $value
        );
    }

    /**
     * Draft products.
     */
    public function scopeDraft(
        Builder $query
    ): Builder {
        return $query->where(
            'status',
            ProductStatus::DRAFT->value
        );
    }

    /**
     * Products awaiting moderation.
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
     * Approved products.
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
     * Rejected products.
     */
    public function scopeRejected(
        Builder $query
    ): Builder {
        return $query->where(
            'status',
            ProductStatus::REJECTED->value
        );
    }

    /**
     * Suspended products.
     */
    public function scopeSuspended(
        Builder $query
    ): Builder {
        return $query->where(
            'status',
            ProductStatus::SUSPENDED->value
        );
    }

    /**
     * Archived products.
     */
    public function scopeArchived(
        Builder $query
    ): Builder {
        return $query->where(
            'status',
            ProductStatus::ARCHIVED->value
        );
    }

    /**
     * Search product identity, description, seller, brand and variant data.
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

        $escaped = addcslashes(
            $search,
            '\\%_'
        );

        $like = "%{$escaped}%";

        return $query->where(
            static function (
                Builder $searchQuery
            ) use ($like): void {
                $searchQuery
                    ->where(
                        'name',
                        'like',
                        $like
                    )
                    ->orWhere(
                        'slug',
                        'like',
                        $like
                    )
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
                        'brand',
                        static function (
                            Builder $brandQuery
                        ) use ($like): void {
                            $brandQuery
                                ->where(
                                    'name',
                                    'like',
                                    $like
                                )
                                ->orWhere(
                                    'slug',
                                    'like',
                                    $like
                                );
                        }
                    )
                    ->orWhereHas(
                        'sellerProfile',
                        static function (
                            Builder $sellerQuery
                        ) use ($like): void {
                            $sellerQuery
                                ->where(
                                    'legal_business_name',
                                    'like',
                                    $like
                                )
                                ->orWhere(
                                    'trading_name',
                                    'like',
                                    $like
                                );
                        }
                    )
                    ->orWhereHas(
                        'variants',
                        static function (
                            Builder $variantQuery
                        ) use ($like): void {
                            $variantQuery
                                ->where(
                                    'name',
                                    'like',
                                    $like
                                )
                                ->orWhere(
                                    'sku',
                                    'like',
                                    $like
                                )
                                ->orWhere(
                                    'barcode',
                                    'like',
                                    $like
                                );
                        }
                    );
            }
        );
    }

    /**
     * Products eligible for the public customer catalog.
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
                        SellerProfileStatus::APPROVED
                            ->value
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
     * Products with available stock or backordering enabled.
     */
    public function scopeInStock(
        Builder $query
    ): Builder {
        return $query->whereHas(
            'activeVariants.inventoryStock',
            static function (
                Builder $stockQuery
            ): void {
                $stockQuery->where(
                    static function (
                        Builder $availableQuery
                    ): void {
                        $availableQuery
                            ->whereColumn(
                                'inventory_stocks.quantity_on_hand',
                                '>',
                                'inventory_stocks.quantity_reserved'
                            )
                            ->orWhere(
                                'allow_backorder',
                                true
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
     * Return the product status as a string.
     */
    public function statusValue(): string
    {
        return self::enumValue(
            $this->status
        );
    }

    /**
     * Return the product condition as a string.
     */
    public function conditionValue(): string
    {
        return self::enumValue(
            $this->condition
        );
    }

    /**
     * Determine whether the product has a specific status.
     */
    public function hasStatus(
        ProductStatus|string $status
    ): bool {
        $expected = $status instanceof ProductStatus
            ? $status->value
            : $status;

        return $this->statusValue()
            === $expected;
    }

    public function isDraft(): bool
    {
        return $this->hasStatus(
            ProductStatus::DRAFT
        );
    }

    public function isPendingReview(): bool
    {
        return $this->hasStatus(
            ProductStatus::PENDING_REVIEW
        );
    }

    public function isApproved(): bool
    {
        return $this->hasStatus(
            ProductStatus::APPROVED
        );
    }

    public function isRejected(): bool
    {
        return $this->hasStatus(
            ProductStatus::REJECTED
        );
    }

    public function isSuspended(): bool
    {
        return $this->hasStatus(
            ProductStatus::SUSPENDED
        );
    }

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
            $this->statusValue(),
            [
                ProductStatus::DRAFT->value,
                ProductStatus::REJECTED->value,
                ProductStatus::APPROVED->value,
            ],
            true
        );
    }

    /**
     * Determine whether the product may enter moderation.
     */
    public function canBeSubmittedForReview(): bool
    {
        return in_array(
            $this->statusValue(),
            [
                ProductStatus::DRAFT->value,
                ProductStatus::REJECTED->value,
            ],
            true
        );
    }

    /**
     * Determine whether the product is visible to customers.
     */
    public function isPubliclyVisible(): bool
    {
        if (!$this->isApproved()) {
            return false;
        }

        $seller = $this->resolvedSellerProfile();

        if (
            !$seller instanceof SellerProfile
            || self::enumValue($seller->status)
                !== SellerProfileStatus::APPROVED->value
        ) {
            return false;
        }

        $category = $this->resolvedCategory();

        if (
            !$category instanceof Category
            || !$category->is_active
        ) {
            return false;
        }

        $brand = $this->resolvedBrand();

        if (
            $this->brand_id !== null
            && (
                !$brand instanceof Brand
                || !$brand->is_active
            )
        ) {
            return false;
        }

        return $this->hasSellableVariant();
    }

    /*
    |--------------------------------------------------------------------------
    | Specification helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Return normalized specification values.
     *
     * @return array<string, mixed>
     */
    public function specificationValues(): array
    {
        return self::normalizeSpecificationValues(
            $this->specifications
        );
    }

    /**
     * Return one specification value by its normalized code.
     */
    public function specificationValue(
        string $code,
        mixed $default = null
    ): mixed {
        $code = self::normalizeSpecificationCode(
            $code
        );

        return $this->specificationValues()[
            $code
        ] ?? $default;
    }

    /**
     * Determine whether a meaningful specification value exists.
     */
    public function hasSpecificationValue(
        string $code
    ): bool {
        $code = self::normalizeSpecificationCode(
            $code
        );

        $values = $this->specificationValues();

        return array_key_exists(
            $code,
            $values
        ) && self::valueIsPresent(
            $values[$code]
        );
    }

    /**
     * Set one specification value.
     */
    public function setSpecificationValue(
        string $code,
        mixed $value
    ): self {
        $code = self::normalizeSpecificationCode(
            $code
        );

        if ($code === '') {
            return $this;
        }

        $values = $this->specificationValues();

        if (!self::valueIsPresent($value)) {
            unset($values[$code]);
        } else {
            $values[$code] = $value;
        }

        $this->specifications = $values;

        return $this;
    }

    /**
     * Remove one specification value.
     */
    public function removeSpecificationValue(
        string $code
    ): self {
        $code = self::normalizeSpecificationCode(
            $code
        );

        $values = $this->specificationValues();

        unset($values[$code]);

        $this->specifications = $values;

        return $this;
    }

    /**
     * Return required specification codes currently missing from the product.
     *
     * @return array<int, string>
     */
    public function missingRequiredSpecifications(): array
    {
        $category = $this->resolvedCategory();

        if (!$category instanceof Category) {
            return [];
        }

        return $category
            ->effectiveSpecificationAssignments()
            ->filter(
                static fn (
                    CategorySpecification $assignment
                ): bool => $assignment->isRequired()
            )
            ->reject(
                function (
                    CategorySpecification $assignment
                ): bool {
                    $code = $assignment->code();

                    if (
                        $this->hasSpecificationValue(
                            $code
                        )
                    ) {
                        return true;
                    }

                    return self::valueIsPresent(
                        $assignment
                            ->effectiveDefaultValue()
                    );
                }
            )
            ->map(
                static fn (
                    CategorySpecification $assignment
                ): string => $assignment->code()
            )
            ->values()
            ->all();
    }

    /**
     * Return required specification validation errors.
     *
     * @return array<string, array<int, string>>
     */
    public function specificationReadinessErrors(): array
    {
        $errors = [];

        $category = $this->resolvedCategory();

        if (!$category instanceof Category) {
            return [
                'category' => [
                    'The product must belong to a valid category.',
                ],
            ];
        }

        $assignments = $category
            ->effectiveSpecificationAssignments()
            ->keyBy(
                static fn (
                    CategorySpecification $assignment
                ): string => $assignment->code()
            );

        foreach (
            $this->missingRequiredSpecifications()
            as $code
        ) {
            $assignment = $assignments->get(
                $code
            );

            $label =
                $assignment instanceof
                CategorySpecification
                    ? $assignment->effectiveLabel()
                    : Str::headline($code);

            $errors[
                "specifications.{$code}"
            ][] = sprintf(
                'The %s specification is required.',
                $label
            );
        }

        return $errors;
    }

    /*
    |--------------------------------------------------------------------------
    | Catalog readiness helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether at least one active variant has positive pricing.
     */
    public function hasSellableVariant(): bool
    {
        return $this
            ->activeVariants()
            ->whereHas(
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
            )
            ->exists();
    }

    /**
     * Determine whether inventory is configured for an active variant.
     */
    public function hasConfiguredInventory(): bool
    {
        return $this
            ->activeVariants()
            ->whereHas(
                'inventoryStock'
            )
            ->exists();
    }

    /**
     * Determine whether stock is currently available.
     */
    public function hasAvailableStock(): bool
    {
        return $this
            ->activeVariants()
            ->whereHas(
                'inventoryStock',
                static function (
                    Builder $stockQuery
                ): void {
                    $stockQuery->where(
                        static function (
                            Builder $availableQuery
                        ): void {
                            $availableQuery
                                ->whereColumn(
                                    'inventory_stocks.quantity_on_hand',
                                    '>',
                                    'inventory_stocks.quantity_reserved'
                                )
                                ->orWhere(
                                    'allow_backorder',
                                    true
                                );
                        }
                    );
                }
            )
            ->exists();
    }

    /**
     * Return all product publication-readiness problems.
     *
     * @return array<string, array<int, string>>
     */
    public function publicationReadinessErrors(): array
    {
        $errors = [];

        $seller = $this->resolvedSellerProfile();

        if (
            !$seller instanceof SellerProfile
            || self::enumValue($seller->status)
                !== SellerProfileStatus::APPROVED->value
        ) {
            $errors['seller'][] =
                'The seller business must be approved before submitting products.';
        }

        $category = $this->resolvedCategory();

        if (!$category instanceof Category) {
            $errors['category'][] =
                'The product must belong to a valid category.';
        } elseif (!$category->is_active) {
            $errors['category'][] =
                'The selected product category is inactive.';
        }

        $brand = $this->resolvedBrand();

        if (
            $this->brand_id !== null
            && (
                !$brand instanceof Brand
                || !$brand->is_active
            )
        ) {
            $errors['brand'][] =
                'The selected product brand is inactive or unavailable.';
        }

        if (
            trim((string) $this->name) === ''
        ) {
            $errors['name'][] =
                'The product name is required.';
        }

        if (
            trim(
                (string) $this->short_description
            ) === ''
            && trim(
                (string) $this->description
            ) === ''
        ) {
            $errors['description'][] =
                'Provide a short description or full product description.';
        }

        foreach (
            $this->specificationReadinessErrors()
            as $field => $messages
        ) {
            foreach ($messages as $message) {
                $errors[$field][] = $message;
            }
        }

        if (
            !$this
                ->activeVariants()
                ->exists()
        ) {
            $errors['variants'][] =
                'Create at least one active product variant.';
        }

        if (!$this->hasSellableVariant()) {
            $errors['pricing'][] =
                'At least one active variant must have a selling price greater than zero.';
        }

        if (!$this->hasConfiguredInventory()) {
            $errors['inventory'][] =
                'Configure inventory for at least one active product variant.';
        }

        if (!$this->media()->exists()) {
            $errors['media'][] =
                'Upload at least one product image.';
        }

        foreach (
            $this->returnPolicyReadinessErrors()
            as $field => $messages
        ) {
            foreach ($messages as $message) {
                $errors[$field][] = $message;
            }
        }

        return $errors;
    }

    /**
     * Determine whether the product is ready for moderation submission.
     */
    public function isReadyForPublication(): bool
    {
        return $this->publicationReadinessErrors()
            === [];
    }

    /*
    |--------------------------------------------------------------------------
    | Return-policy helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether the product has an active return policy.
     */
    public function hasActiveReturnPolicy(): bool
    {
        if ($this->relationLoaded('returnPolicy')) {
            return $this->returnPolicy
                instanceof ProductReturnPolicy
                && $this->returnPolicy->is_active;
        }

        return $this
            ->activeReturnPolicy()
            ->exists();
    }

    /**
     * Determine whether customers may return this product.
     */
    public function isReturnable(): bool
    {
        $policy = $this
            ->resolvedReturnPolicy();

        return $policy?->allowsReturns()
            ?? false;
    }

    /**
     * Return customer-safe return-policy information.
     *
     * @return array<string, mixed>|null
     */
    public function customerReturnPolicy(): ?array
    {
        $policy = $this
            ->resolvedReturnPolicy();

        if (
            !$policy instanceof
            ProductReturnPolicy
        ) {
            return null;
        }

        return $policy->toCustomerPolicy();
    }

    /**
     * Return problems that prevent return-policy readiness.
     *
     * @return array<string, array<int, string>>
     */
    public function returnPolicyReadinessErrors(): array
    {
        $policy = $this
            ->resolvedReturnPolicy();

        if (
            !$policy instanceof
            ProductReturnPolicy
        ) {
            return [
                'return_policy' => [
                    'Configure a product return policy before submitting the product for moderation.',
                ],
            ];
        }

        if (!$policy->is_active) {
            return [
                'return_policy' => [
                    'The product return policy must be active before moderation submission.',
                ],
            ];
        }

        $errors = [];

        foreach (
            $policy->configurationErrors()
            as $field => $messages
        ) {
            $errorField =
                "return_policy.{$field}";

            foreach ($messages as $message) {
                $errors[$errorField][] =
                    $message;
            }
        }

        return $errors;
    }

    /**
     * Determine whether the return policy is complete.
     */
    public function hasValidReturnPolicy(): bool
    {
        return $this
            ->returnPolicyReadinessErrors()
            === [];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationship resolvers
    |--------------------------------------------------------------------------
    */

    private function resolvedSellerProfile(): ?SellerProfile
    {
        if (
            $this->relationLoaded(
                'sellerProfile'
            )
        ) {
            $seller = $this->getRelation(
                'sellerProfile'
            );

            return $seller instanceof SellerProfile
                ? $seller
                : null;
        }

        return $this
            ->sellerProfile()
            ->first();
    }

    private function resolvedCategory(): ?Category
    {
        if (
            $this->relationLoaded(
                'category'
            )
        ) {
            $category = $this->getRelation(
                'category'
            );

            return $category instanceof Category
                ? $category
                : null;
        }

        return $this
            ->category()
            ->first();
    }

    private function resolvedBrand(): ?Brand
    {
        if ($this->brand_id === null) {
            return null;
        }

        if (
            $this->relationLoaded(
                'brand'
            )
        ) {
            $brand = $this->getRelation(
                'brand'
            );

            return $brand instanceof Brand
                ? $brand
                : null;
        }

        return $this
            ->brand()
            ->first();
    }

    private function resolvedReturnPolicy(): ?ProductReturnPolicy
    {
        if (
            $this->relationLoaded(
                'returnPolicy'
            )
        ) {
            $policy = $this->getRelation(
                'returnPolicy'
            );

            return $policy
                instanceof ProductReturnPolicy
                    ? $policy
                    : null;
        }

        return $this
            ->returnPolicy()
            ->first();
    }

    /*
    |--------------------------------------------------------------------------
    | Normalization helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Normalize the product specification JSON object.
     *
     * @return array<string, mixed>
     */
    private static function normalizeSpecificationValues(
        mixed $values
    ): array {
        if (!is_array($values)) {
            return [];
        }

        $normalized = [];

        foreach ($values as $code => $value) {
            if (
                !is_string($code)
                && !is_int($code)
            ) {
                continue;
            }

            $normalizedCode =
                self::normalizeSpecificationCode(
                    (string) $code
                );

            if ($normalizedCode === '') {
                continue;
            }

            $normalized[$normalizedCode] =
                $value;
        }

        return $normalized;
    }

    /**
     * Normalize one specification code.
     */
    private static function normalizeSpecificationCode(
        string $code
    ): string {
        return Str::snake(
            trim($code)
        );
    }

    /**
     * Determine whether a specification value is meaningfully present.
     *
     * false and zero are valid values.
     */
    private static function valueIsPresent(
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

    /**
     * Return a string value from an enum or scalar.
     */
    private static function enumValue(
        mixed $value
    ): string {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return (string) $value;
    }

    /**
     * Trim nullable text.
     */
    private static function nullableTrim(
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
}