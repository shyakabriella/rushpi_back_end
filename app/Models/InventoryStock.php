<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryStock extends Model
{
    use HasFactory;

    /**
     * Inventory fields that may be assigned safely.
     *
     * Controllers must not update these fields directly.
     * Inventory changes will later pass through InventoryService.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'product_variant_id',
        'quantity_on_hand',
        'quantity_reserved',
        'reorder_level',
        'allow_backorder',
    ];

    /**
     * Inventory attribute casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity_on_hand' => 'integer',
            'quantity_reserved' => 'integer',
            'reorder_level' => 'integer',
            'allow_backorder' => 'boolean',
        ];
    }

    /**
     * Product variant that owns this inventory record.
     */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /**
     * Return the quantity that is currently available.
     *
     * Available quantity is physical stock minus
     * stock reserved for customer orders.
     */
    public function availableQuantity(): int
    {
        return max(
            0,
            $this->quantity_on_hand - $this->quantity_reserved
        );
    }

    /**
     * Determine whether the variant has available stock.
     *
     * Backordered variants remain sellable even when
     * their available quantity is zero.
     */
    public function isInStock(): bool
    {
        return $this->allow_backorder
            || $this->availableQuantity() > 0;
    }

    /**
     * Determine whether the inventory is at or below
     * its configured reorder level.
     */
    public function isLowStock(): bool
    {
        return $this->availableQuantity()
            <= $this->reorder_level;
    }

    /**
     * Determine whether the inventory is completely depleted.
     */
    public function isOutOfStock(): bool
    {
        return ! $this->allow_backorder
            && $this->availableQuantity() <= 0;
    }

    /**
     * Determine whether a requested quantity can be sold.
     */
    public function canFulfill(int $quantity): bool
    {
        if ($quantity <= 0) {
            return false;
        }

        if ($this->allow_backorder) {
            return true;
        }

        return $this->availableQuantity() >= $quantity;
    }

    /**
     * Determine whether a quantity can be reserved.
     */
    public function canReserve(int $quantity): bool
    {
        return $this->canFulfill($quantity);
    }

    /**
     * Return a customer-safe stock status.
     */
    public function stockStatus(): string
    {
        if ($this->isOutOfStock()) {
            return 'out_of_stock';
        }

        if ($this->isLowStock()) {
            return 'low_stock';
        }

        return 'in_stock';
    }

    /**
     * Limit results to inventory with available stock.
     */
    public function scopeInStock(Builder $query): Builder
    {
        return $query->where(
            function (Builder $stockQuery): void {
                $stockQuery
                    ->where('allow_backorder', true)
                    ->orWhereColumn(
                        'quantity_on_hand',
                        '>',
                        'quantity_reserved'
                    );
            }
        );
    }

    /**
     * Limit results to inventory without available stock.
     */
    public function scopeOutOfStock(Builder $query): Builder
    {
        return $query
            ->where('allow_backorder', false)
            ->whereColumn(
                'quantity_on_hand',
                '<=',
                'quantity_reserved'
            );
    }

    /**
     * Limit inventory to one product variant.
     */
    public function scopeForVariant(
        Builder $query,
        int $productVariantId
    ): Builder {
        return $query->where(
            'product_variant_id',
            $productVariantId
        );
    }
}