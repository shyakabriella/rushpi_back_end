<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ServiceOrder extends Model
{
    use HasFactory;

    public const MODE_VOLUME = 'volume';
    public const MODE_WEIGHT = 'weight';
    public const MODE_AMOUNT = 'amount';

    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_READY = 'ready';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const PAYMENT_UNPAID = 'unpaid';
    public const PAYMENT_PAID = 'paid';
    public const PAYMENT_FAILED = 'failed';
    public const PAYMENT_REFUNDED = 'refunded';

    protected $fillable = [
        'public_id',
        'order_number',

        'user_id',
        'customer_name',
        'customer_phone',

        /*
         * Legacy first-item snapshot.
         * Kept for backward compatibility.
         */
        'service_id',
        'service_name',

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

        'total_price_rwf',

        /*
         * Multi-item checkout.
         */
        'item_count',
        'subtotal_amount_rwf',

        'delivery_method',
        'delivery_fee_rwf',

        'total_amount_rwf',

        'delivery_address',
        'delivery_latitude',
        'delivery_longitude',

        'delivery_city',
        'delivery_district',
        'delivery_region',
        'delivery_country',

        'is_kigali',
        'location_note',

        'customer_note',

        'status',
        'payment_status',

        'confirmed_at',
        'completed_at',
        'cancelled_at',
    ];

    protected $hidden = [
        'id',
        'user_id',
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

        'total_price_rwf' =>
            'decimal:2',

        'subtotal_amount_rwf' =>
            'decimal:2',

        'delivery_fee_rwf' =>
            'decimal:2',

        'total_amount_rwf' =>
            'decimal:2',

        'delivery_latitude' =>
            'decimal:7',

        'delivery_longitude' =>
            'decimal:7',

        'is_kigali' =>
            'boolean',

        'confirmed_at' =>
            'datetime',

        'completed_at' =>
            'datetime',

        'cancelled_at' =>
            'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(
            function (
                ServiceOrder $order
            ): void {
                if (! $order->public_id) {
                    $order->public_id =
                        (string) Str::uuid();
                }

                if (! $order->order_number) {
                    $order->order_number =
                        static::generateOrderNumber();
                }
            }
        );
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class
        );
    }

    /*
     * Legacy relation.
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(
            Service::class
        );
    }

    public function items(): HasMany
    {
        return $this->hasMany(
            ServiceOrderItem::class
        );
    }

    private static function generateOrderNumber(): string
    {
        do {
            $number =
                'PNT-'
                . now()->format('Ymd')
                . '-'
                . random_int(
                    100000,
                    999999
                );
        } while (
            static::query()
                ->where(
                    'order_number',
                    $number
                )
                ->exists()
        );

        return $number;
    }
}
