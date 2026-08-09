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
            'metadata' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * Generate the public ID and protect historical records.
     */
    protected static function booted(): void
    {
        static::creating(
            function (StockMovement $movement): void {
                if (blank($movement->public_id)) {
                    $movement->public_id = (string) Str::ulid();
                }
            }
        );

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

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Product variant affected by this movement.
     */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(
            ProductVariant::class,
            'product_variant_id'
        );
    }

    /**
     * Relationship alias expected by seller resources/controllers.
     */
    public function variant(): BelongsTo
    {
        return $this->productVariant();
    }

    /**
     * Seller business that owns the inventory.
     */
    public function sellerProfile(): BelongsTo
    {
        return $this->belongsTo(
            SellerProfile::class,
            'seller_profile_id'
        );
    }

    /**
     * User who performed the stock operation.
     */
    public function performer(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'performed_by'
        );
    }

    /**
     * Relationship alias expected by seller resources/controllers.
     */
    public function performedBy(): BelongsTo
    {
        return $this->performer();
    }

    /*
    |--------------------------------------------------------------------------
    | Query scopes
    |--------------------------------------------------------------------------
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

    public function scopeForSeller(
        Builder $query,
        int $sellerProfileId
    ): Builder {
        return $query->where(
            'seller_profile_id',
            $sellerProfileId
        );
    }

    public function scopeOfType(
        Builder $query,
        StockMovementType|string $type
    ): Builder {
        $value = $type instanceof StockMovementType
            ? $type->value
            : $type;

        return $query->where(
            'movement_type',
            $value
        );
    }

    public function scopeLatestFirst(
        Builder $query
    ): Builder {
        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    public function scopeForReference(
        Builder $query,
        string $referenceType,
        int|string $referenceId
    ): Builder {
        return $query
            ->where(
                'reference_type',
                $referenceType
            )
            ->where(
                'reference_id',
                $referenceId
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function onHandChange(): int
    {
        return (int) $this->quantity_on_hand_after
            - (int) $this->quantity_on_hand_before;
    }

    public function reservedChange(): int
    {
        return (int) $this->quantity_reserved_after
            - (int) $this->quantity_reserved_before;
    }

    public function increasedOnHand(): bool
    {
        return $this->onHandChange() > 0;
    }

    public function decreasedOnHand(): bool
    {
        return $this->onHandChange() < 0;
    }

    public function changedReservation(): bool
    {
        return $this->reservedChange() !== 0;
    }

    public function availableBefore(): int
    {
        return max(
            0,
            (int) $this->quantity_on_hand_before
                - (int) $this->quantity_reserved_before
        );
    }

    public function availableAfter(): int
    {
        return max(
            0,
            (int) $this->quantity_on_hand_after
                - (int) $this->quantity_reserved_after
        );
    }
}