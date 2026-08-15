<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ServiceOrderItem extends Model
{
    protected $fillable = [
        'public_id',

        'service_order_id',
        'service_id',

        'service_name',
        'paint_type',
        'color_name',

        'order_mode',

        'requested_quantity',
        'requested_unit',
        'requested_amount_rwf',

        'equivalent_ml',
        'equivalent_l',
        'equivalent_g',
        'equivalent_kg',

        'reference_quantity',
        'reference_unit',
        'reference_price_rwf',

        'density_kg_per_l',

        'stock_quantity_deducted',
        'stock_unit',

        'line_total_rwf',
    ];

    protected $hidden = [
        'id',
        'service_order_id',
        'service_id',
    ];

    protected $casts = [
        'requested_quantity' =>
            'decimal:6',

        'requested_amount_rwf' =>
            'decimal:2',

        'equivalent_ml' =>
            'decimal:6',

        'equivalent_l' =>
            'decimal:6',

        'equivalent_g' =>
            'decimal:6',

        'equivalent_kg' =>
            'decimal:6',

        'reference_quantity' =>
            'decimal:6',

        'reference_price_rwf' =>
            'decimal:2',

        'density_kg_per_l' =>
            'decimal:6',

        'stock_quantity_deducted' =>
            'decimal:6',

        'line_total_rwf' =>
            'decimal:2',
    ];

    protected static function booted(): void
    {
        static::creating(
            function (
                ServiceOrderItem $item
            ): void {
                if (! $item->public_id) {
                    $item->public_id =
                        (string) Str::uuid();
                }
            }
        );
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(
            ServiceOrder::class,
            'service_order_id'
        );
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(
            Service::class
        );
    }
}
