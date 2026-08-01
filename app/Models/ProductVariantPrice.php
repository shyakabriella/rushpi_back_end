<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductVariantPrice extends Model
{
    use HasFactory;

    /**
     * Fields that may be assigned safely.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'product_variant_id',
        'currency',
        'selling_price',
        'compare_at_price',
        'cost_price',
        'created_by',
        'updated_by',
    ];

    /**
     * Price attribute casts.
     *
     * Laravel decimal casts return formatted strings,
     * which helps avoid unnecessary floating-point changes.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'selling_price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
        ];
    }

    /**
     * Normalize the currency code before saving.
     */
    protected static function booted(): void
    {
        static::creating(
            function (ProductVariantPrice $price): void {
                $price->currency = strtoupper(
                    trim($price->currency ?: 'RWF')
                );
            }
        );

        static::updating(
            function (ProductVariantPrice $price): void {
                if ($price->isDirty('currency')) {
                    $price->currency = strtoupper(
                        trim($price->currency)
                    );
                }
            }
        );
    }

    /**
     * Product variant that owns this price.
     */
    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    /**
     * User who created the price record.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            related: User::class,
            foreignKey: 'created_by'
        );
    }

    /**
     * User who last updated the price record.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(
            related: User::class,
            foreignKey: 'updated_by'
        );
    }

    /**
     * Limit prices to one currency.
     */
    public function scopeForCurrency(
        Builder $query,
        string $currency
    ): Builder {
        return $query->where(
            'currency',
            strtoupper(trim($currency))
        );
    }

    /**
     * Limit results to prices within a customer price range.
     */
    public function scopeWithinRange(
        Builder $query,
        ?string $minimumPrice = null,
        ?string $maximumPrice = null
    ): Builder {
        return $query
            ->when(
                $minimumPrice !== null,
                fn (Builder $priceQuery): Builder => $priceQuery
                    ->where(
                        'selling_price',
                        '>=',
                        $minimumPrice
                    )
            )
            ->when(
                $maximumPrice !== null,
                fn (Builder $priceQuery): Builder => $priceQuery
                    ->where(
                        'selling_price',
                        '<=',
                        $maximumPrice
                    )
            );
    }

    /**
     * Determine whether a valid discount price exists.
     */
    public function isDiscounted(): bool
    {
        if ($this->compare_at_price === null) {
            return false;
        }

        return (float) $this->compare_at_price
            > (float) $this->selling_price;
    }

    /**
     * Return the discount percentage for display.
     */
    public function discountPercentage(): float
    {
        if (! $this->isDiscounted()) {
            return 0.0;
        }

        $compareAtPrice = (float) $this->compare_at_price;
        $sellingPrice = (float) $this->selling_price;

        return round(
            (($compareAtPrice - $sellingPrice) / $compareAtPrice) * 100,
            2
        );
    }

    /**
     * Return a formatted public selling price.
     */
    public function formattedSellingPrice(): string
    {
        return number_format(
            (float) $this->selling_price,
            2,
            '.',
            ','
        ).' '.$this->currency;
    }
}