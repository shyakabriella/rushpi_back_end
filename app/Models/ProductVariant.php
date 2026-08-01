<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ProductVariant extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * Fields that may be assigned safely.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'product_id',
        'sku',
        'barcode',
        'name',
        'attributes',
        'weight_grams',
        'length_cm',
        'width_cm',
        'height_cm',
        'is_default',
        'is_active',
        'sort_order',
    ];

    /**
     * Variant attribute casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'weight_grams' => 'integer',
            'length_cm' => 'decimal:2',
            'width_cm' => 'decimal:2',
            'height_cm' => 'decimal:2',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Generate the public identifier automatically.
     */
    protected static function booted(): void
    {
        static::creating(function (ProductVariant $variant): void {
            if (blank($variant->public_id)) {
                $variant->public_id = (string) Str::ulid();
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
     * Product that owns this variant.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Current price assigned to this variant.
     */
    public function price(): HasOne
    {
        return $this->hasOne(ProductVariantPrice::class);
    }

    /**
     * Current inventory record for this variant.
     */
    public function inventoryStock(): HasOne
    {
        return $this->hasOne(InventoryStock::class);
    }

    /**
     * Images assigned specifically to this variant.
     */
    public function media(): HasMany
    {
        return $this->hasMany(ProductMedia::class)
            ->orderByDesc('is_primary')
            ->orderBy('sort_order');
    }

    /**
     * Inventory movement history.
     */
    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class)
            ->latest('created_at');
    }

    /**
     * Limit results to active variants.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Limit results to the default variant.
     */
    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    /**
     * Limit variants to one product.
     */
    public function scopeForProduct(
        Builder $query,
        int $productId
    ): Builder {
        return $query->where('product_id', $productId);
    }

    /**
     * Search variants by name, SKU or barcode.
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
            function (Builder $variantQuery) use ($search): void {
                $variantQuery
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere('sku', 'like', '%'.$search.'%')
                    ->orWhere('barcode', 'like', '%'.$search.'%');
            }
        );
    }

    /**
     * Determine whether this variant is sellable.
     *
     * A variant must be active, have a price and belong
     * to an approved product.
     */
    public function isSellable(): bool
    {
        return $this->is_active
            && $this->price !== null
            && $this->product?->isPubliclyVisible() === true;
    }

    /**
     * Return the current available quantity.
     */
    public function availableQuantity(): int
    {
        if ($this->inventoryStock === null) {
            return 0;
        }

        return $this->inventoryStock->availableQuantity();
    }

    /**
     * Determine whether stock is currently available.
     */
    public function isInStock(): bool
    {
        if ($this->inventoryStock === null) {
            return false;
        }

        return $this->inventoryStock->allow_backorder
            || $this->availableQuantity() > 0;
    }
}