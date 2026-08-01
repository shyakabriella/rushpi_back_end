<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProductCondition;
use App\Enums\ProductStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * Seller-editable and safely assignable fields.
     *
     * Moderation fields such as status, approved_by,
     * approved_at and rejection_reason are intentionally
     * excluded from mass assignment.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'seller_profile_id',
        'category_id',
        'brand_id',
        'created_by',
        'updated_by',
        'name',
        'slug',
        'short_description',
        'description',
        'condition',
        'warranty_months',
        'specifications',
    ];

    /**
     * Product attribute casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'condition' => ProductCondition::class,
            'status' => ProductStatus::class,
            'specifications' => 'array',
            'warranty_months' => 'integer',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'suspended_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    /**
     * Generate product public ID, slug and default status.
     */
    protected static function booted(): void
    {
        static::creating(function (Product $product): void {
            if (blank($product->public_id)) {
                $product->public_id = (string) Str::ulid();
            }

            if (blank($product->slug)) {
                $product->slug = static::generateUniqueSlug(
                    sellerProfileId: (int) $product->seller_profile_id,
                    name: $product->name
                );
            }

            if (blank($product->status)) {
                $product->status = ProductStatus::DRAFT;
            }

            if (blank($product->condition)) {
                $product->condition = ProductCondition::NEW;
            }
        });

        static::updating(function (Product $product): void {
            if (
                $product->isDirty('name')
                && ! $product->isDirty('slug')
            ) {
                $product->slug = static::generateUniqueSlug(
                    sellerProfileId: (int) $product->seller_profile_id,
                    name: $product->name,
                    ignoreId: $product->id
                );
            }
        });
    }

    /**
     * Use public_id for route model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * Seller business owning this product.
     */
    public function sellerProfile(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class);
    }

    /**
     * Product category.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Optional product brand.
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * User who originally created the product.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            related: User::class,
            foreignKey: 'created_by'
        );
    }

    /**
     * User who last updated the product.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(
            related: User::class,
            foreignKey: 'updated_by'
        );
    }

    /**
     * Administrator who approved the product.
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(
            related: User::class,
            foreignKey: 'approved_by'
        );
    }

    /**
     * Product variants.
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    /**
     * Active product variants.
     */
    public function activeVariants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('sort_order');
    }

    /**
     * Product images and other supported media.
     */
    public function media(): HasMany
    {
        return $this->hasMany(ProductMedia::class)
            ->orderByDesc('is_primary')
            ->orderBy('sort_order');
    }

    /**
     * Product moderation history.
     */
    public function moderationReviews(): HasMany
    {
        return $this->hasMany(ProductModerationReview::class)
            ->latest('created_at');
    }

    /**
     * Limit results to products belonging to one seller.
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
     * Limit results to products with a particular status.
     */
    public function scopeWithStatus(
        Builder $query,
        ProductStatus|string $status
    ): Builder {
        $value = $status instanceof ProductStatus
            ? $status->value
            : $status;

        return $query->where('status', $value);
    }

    /**
     * Limit results to products approved for public access.
     */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where(
            'status',
            ProductStatus::APPROVED->value
        );
    }

    /**
     * Limit results to products awaiting moderation.
     */
    public function scopePendingReview(Builder $query): Builder
    {
        return $query->where(
            'status',
            ProductStatus::PENDING_REVIEW->value
        );
    }

    /**
     * Search basic product catalog fields.
     */
    public function scopeSearch(
        Builder $query,
        ?string $search
    ): Builder {
        $search = trim((string) $search);

        if ($search === '') {
            return $query;
        }

        return $query->where(
            function (Builder $productQuery) use ($search): void {
                $productQuery
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere(
                        'short_description',
                        'like',
                        '%'.$search.'%'
                    )
                    ->orWhere(
                        'description',
                        'like',
                        '%'.$search.'%'
                    );
            }
        );
    }

    /**
     * Determine whether the product is publicly visible.
     */
    public function isPubliclyVisible(): bool
    {
        return $this->status === ProductStatus::APPROVED
            && $this->deleted_at === null;
    }

    /**
     * Determine whether the seller may edit this product.
     */
    public function canBeEditedBySeller(): bool
    {
        return $this->status->canBeEditedBySeller();
    }

    /**
     * Generate a slug unique within the seller business.
     */
    private static function generateUniqueSlug(
        int $sellerProfileId,
        string $name,
        ?int $ignoreId = null
    ): string {
        $baseSlug = Str::slug($name);

        if ($baseSlug === '') {
            $baseSlug = 'product';
        }

        $slug = $baseSlug;
        $number = 2;

        while (
            static::query()
                ->withTrashed()
                ->where('seller_profile_id', $sellerProfileId)
                ->when(
                    $ignoreId !== null,
                    fn (Builder $query): Builder => $query->where(
                        'id',
                        '!=',
                        $ignoreId
                    )
                )
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $baseSlug.'-'.$number;
            $number++;
        }

        return $slug;
    }
}