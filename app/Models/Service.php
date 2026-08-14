<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class Service extends Model
{
    public const UNITS = [
        'ml',
        'l',
        'g',
        'kg',
    ];

    protected $fillable = [
        'public_id',
        'service_type',
        'name',
        'slug',
        'paint_type',
        'brand_name',
        'color_name',
        'description',

        'reference_quantity',
        'reference_unit',
        'reference_price_rwf',

        'density_kg_per_l',

        'allow_volume_sale',
        'allow_weight_sale',
        'allow_amount_sale',

        'stock_quantity',
        'stock_unit',

        'image_path',

        'is_active',
        'status',
    ];

    protected $casts = [
        'reference_quantity' => 'decimal:4',

        'reference_price_rwf' => 'decimal:2',

        'density_kg_per_l' => 'decimal:4',

        'stock_quantity' => 'decimal:4',

        'allow_volume_sale' => 'boolean',

        'allow_weight_sale' => 'boolean',

        'allow_amount_sale' => 'boolean',

        'is_active' => 'boolean',
    ];

    protected $appends = [
        'image_url',
        'reference_label',
    ];

    protected static function booted(): void
    {
        static::creating(
            function (Service $service): void {
                if (! $service->public_id) {
                    $service->public_id =
                        (string) Str::uuid();
                }

                if (! $service->slug) {
                    $service->slug =
                        static::uniqueSlug(
                            $service->name
                        );
                }
            }
        );
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image_path) {
            return null;
        }

        return Storage::disk('public')
            ->url($this->image_path);
    }

    public function getReferenceLabelAttribute(): string
    {
        return sprintf(
            '%s %s = %s RWF',
            rtrim(
                rtrim(
                    number_format(
                        (float) $this->reference_quantity,
                        4,
                        '.',
                        ''
                    ),
                    '0'
                ),
                '.'
            ),
            strtoupper(
                $this->reference_unit
            ),
            number_format(
                (float) $this->reference_price_rwf,
                0
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | PRICE FOR ANY QUANTITY
    |--------------------------------------------------------------------------
    |
    | Example:
    |
    | Reference:
    | 1 L = 7,000 RWF
    |
    | Customer requests:
    | 143 mL
    |
    | Price:
    | 1,001 RWF
    |
    */
    public function priceFor(
        float $quantity,
        string $unit
    ): float {
        if ($quantity <= 0) {
            throw new InvalidArgumentException(
                'Quantity must be greater than zero.'
            );
        }

        $unit = strtolower(
            trim($unit)
        );

        $referenceUnit =
            strtolower(
                $this->reference_unit
            );

        $converted =
            $this->convertQuantity(
                $quantity,
                $unit,
                $referenceUnit
            );

        $pricePerReferenceUnit =
            (float) $this->reference_price_rwf
            /
            (float) $this->reference_quantity;

        return round(
            $converted
            * $pricePerReferenceUnit,
            2
        );
    }

    /*
    |--------------------------------------------------------------------------
    | QUANTITY CUSTOMER GETS FOR MONEY
    |--------------------------------------------------------------------------
    |
    | Example:
    |
    | 1 L = 7,000 RWF
    |
    | Customer pays:
    | 1,000 RWF
    |
    | Result:
    | 142.857... mL
    |
    */
    public function quantityForAmount(
        float $amount,
        string $outputUnit
    ): float {
        if ($amount <= 0) {
            throw new InvalidArgumentException(
                'Amount must be greater than zero.'
            );
        }

        $referencePrice =
            (float) $this->reference_price_rwf;

        if ($referencePrice <= 0) {
            throw new InvalidArgumentException(
                'Reference price is invalid.'
            );
        }

        $referenceQuantity =
            (
                $amount
                /
                $referencePrice
            )
            *
            (float) $this->reference_quantity;

        return round(
            $this->convertQuantity(
                $referenceQuantity,
                $this->reference_unit,
                $outputUnit
            ),
            6
        );
    }

    /*
    |--------------------------------------------------------------------------
    | UNIT CONVERSION
    |--------------------------------------------------------------------------
    |
    | Supports:
    |
    | mL ↔ L
    | g  ↔ KG
    |
    | If density exists:
    |
    | mL/L ↔ g/KG
    |
    */
    public function convertQuantity(
        float $quantity,
        string $fromUnit,
        string $toUnit
    ): float {
        $fromUnit =
            strtolower(
                trim($fromUnit)
            );

        $toUnit =
            strtolower(
                trim($toUnit)
            );

        if (
            ! in_array(
                $fromUnit,
                self::UNITS,
                true
            )
            ||
            ! in_array(
                $toUnit,
                self::UNITS,
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Unsupported measurement unit.'
            );
        }

        if ($fromUnit === $toUnit) {
            return $quantity;
        }

        $fromVolume =
            $this->isVolumeUnit(
                $fromUnit
            );

        $toVolume =
            $this->isVolumeUnit(
                $toUnit
            );

        /*
         * Volume → Volume
         */
        if (
            $fromVolume
            &&
            $toVolume
        ) {
            $millilitres =
                $fromUnit === 'l'
                    ? $quantity * 1000
                    : $quantity;

            return $toUnit === 'l'
                ? $millilitres / 1000
                : $millilitres;
        }

        /*
         * Weight → Weight
         */
        if (
            ! $fromVolume
            &&
            ! $toVolume
        ) {
            $grams =
                $fromUnit === 'kg'
                    ? $quantity * 1000
                    : $quantity;

            return $toUnit === 'kg'
                ? $grams / 1000
                : $grams;
        }

        $density =
            (float) $this->density_kg_per_l;

        if ($density <= 0) {
            throw new InvalidArgumentException(
                'Paint density is required to convert between weight and volume.'
            );
        }

        /*
         * Important:
         *
         * density kg/L
         * has same numerical value as
         * grams/mL.
         */

        if ($fromVolume) {
            $millilitres =
                $fromUnit === 'l'
                    ? $quantity * 1000
                    : $quantity;

            $grams =
                $millilitres
                * $density;

            return $toUnit === 'kg'
                ? $grams / 1000
                : $grams;
        }

        $grams =
            $fromUnit === 'kg'
                ? $quantity * 1000
                : $quantity;

        $millilitres =
            $grams
            /
            $density;

        return $toUnit === 'l'
            ? $millilitres / 1000
            : $millilitres;
    }

    public function quote(
        float $quantity,
        string $unit
    ): array {
        $price =
            $this->priceFor(
                $quantity,
                $unit
            );

        $result = [
            'requested_quantity' =>
                $quantity,

            'requested_unit' =>
                $unit,

            'price_rwf' =>
                $price,
        ];

        try {
            $ml =
                $this->convertQuantity(
                    $quantity,
                    $unit,
                    'ml'
                );

            $result['equivalent_ml'] =
                round($ml, 6);

            $result['equivalent_l'] =
                round(
                    $ml / 1000,
                    6
                );
        } catch (
            InvalidArgumentException
        ) {
            // No volume conversion.
        }

        try {
            $grams =
                $this->convertQuantity(
                    $quantity,
                    $unit,
                    'g'
                );

            $result['equivalent_g'] =
                round(
                    $grams,
                    6
                );

            $result['equivalent_kg'] =
                round(
                    $grams / 1000,
                    6
                );
        } catch (
            InvalidArgumentException
        ) {
            // No weight conversion.
        }

        return $result;
    }

    public function quoteAmount(
        float $amount,
        string $outputUnit
    ): array {
        $quantity =
            $this->quantityForAmount(
                $amount,
                $outputUnit
            );

        $result = [
            'amount_rwf' =>
                round(
                    $amount,
                    2
                ),

            'quantity' =>
                $quantity,

            'unit' =>
                $outputUnit,
        ];

        try {
            $ml =
                $this->convertQuantity(
                    $quantity,
                    $outputUnit,
                    'ml'
                );

            $result['equivalent_ml'] =
                round($ml, 6);

            $result['equivalent_l'] =
                round(
                    $ml / 1000,
                    6
                );
        } catch (
            InvalidArgumentException
        ) {
            //
        }

        try {
            $grams =
                $this->convertQuantity(
                    $quantity,
                    $outputUnit,
                    'g'
                );

            $result['equivalent_g'] =
                round(
                    $grams,
                    6
                );

            $result['equivalent_kg'] =
                round(
                    $grams / 1000,
                    6
                );
        } catch (
            InvalidArgumentException
        ) {
            //
        }

        return $result;
    }

    private function isVolumeUnit(
        string $unit
    ): bool {
        return in_array(
            $unit,
            [
                'ml',
                'l',
            ],
            true
        );
    }

    private static function uniqueSlug(
        string $name
    ): string {
        $base =
            Str::slug($name)
            ?: 'service';

        $slug =
            $base;

        $counter = 2;

        while (
            static::query()
                ->where(
                    'slug',
                    $slug
                )
                ->exists()
        ) {
            $slug =
                $base
                . '-'
                . $counter;

            $counter++;
        }

        return $slug;
    }
}