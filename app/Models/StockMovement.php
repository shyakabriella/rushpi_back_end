<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StockMovementType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

class StockMovement extends Model
{
    use HasFactory;

    /**
     * Stock movements only have created_at.
     */
    public const UPDATED_AT = null;

    /**
     * Fields that may be assigned when recording
     * an inventory movement.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'product_variant_id',
        'seller_profile_id',
        'performed_by',
        'movement_type',
        'quantity',
        'quantity_on_hand_before',
        'quantity_on_hand_after',
        'quantity_reserved_before',
        'quantity_reserved_after',
        'reference_type',
        'reference_id',
        'reason',
        'metadata',
    ];

    /**
     * Stock movement attribute casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'movement_type' => StockMovementType::class,
            'quantity' => 'integer',
            'quantity_on_hand_before' => 'integer',
            'quantity_on_hand_after' => 'integer',
            'quantity_reserved_before' => 'integer',
            'quantity_reserved_after' => 'integer',
            'reference_id' => 'integer',
            'metadata' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * Generate the public ID and protect historical records.
     */
    protected static function booted(): void
    {
        static::creating(function (StockMovement $movement): void {
            if (blank($movement->public_id)) {
                $movement->public_id = (string) Str::ulid();
            }
        });

        static::updating(function (): never {
            throw new LogicException(
                'Stock movement records cannot be updated.'
            );
        });

        static::deleting(function (): never {
            throw new LogicException(
                'Stock movement records cannot be deleted.'
            );
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
     * Product variant affected by this movement.
     */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /**
     * Seller business that owns the inventory.
     */
    public function sellerProfile(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class);
    }

    /**
     * User who performed the stock operation.
     */
    public function performer(): BelongsTo
    {
        return $this->belongsTo(
            related: User::class,
            foreignKey: 'performed_by'
        );
    }

    /**
     * Limit movements to one product variant.
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

    /**
     * Limit movements to one seller business.
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
     * Limit movements to one movement type.
     */
    public function scopeOfType(
        Builder $query,
        StockMovementType|string $type
    ): Builder {
        $value = $type instanceof StockMovementType
            ? $type->value
            : $type;

        return $query->where('movement_type', $value);
    }

    /**
     * Return movements in newest-first order.
     */
    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    /**
     * Limit movements to a related record.
     *
     * Examples include orders, returns and purchases.
     */
    public function scopeForReference(
        Builder $query,
        string $referenceType,
        int $referenceId
    ): Builder {
        return $query
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId);
    }

    /**
     * Return the change in physical stock.
     */
    public function onHandChange(): int
    {
        return $this->quantity_on_hand_after
            - $this->quantity_on_hand_before;
    }

    /**
     * Return the change in reserved stock.
     */
    public function reservedChange(): int
    {
        return $this->quantity_reserved_after
            - $this->quantity_reserved_before;
    }

    /**
     * Determine whether physical stock increased.
     */
    public function increasedOnHand(): bool
    {
        return $this->onHandChange() > 0;
    }

    /**
     * Determine whether physical stock decreased.
     */
    public function decreasedOnHand(): bool
    {
        return $this->onHandChange() < 0;
    }

    /**
     * Determine whether reserved stock changed.
     */
    public function changedReservation(): bool
    {
        return $this->reservedChange() !== 0;
    }

    /**
     * Return the available quantity before this movement.
     */
    public function availableBefore(): int
    {
        return max(
            0,
            $this->quantity_on_hand_before
                - $this->quantity_reserved_before
        );
    }

    /**
     * Return the available quantity after this movement.
     */
    public function availableAfter(): int
    {
        return max(
            0,
            $this->quantity_on_hand_after
                - $this->quantity_reserved_after
        );
    }
}