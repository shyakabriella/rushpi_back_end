<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

final class ProductOrder extends Model
{
    use HasFactory;
    use HasUlids;

    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SHIPPED = 'shipped';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_CANCELLED = 'cancelled';

    public const PAYMENT_PENDING = 'pending';
    public const PAYMENT_PAID = 'paid';
    public const PAYMENT_FAILED = 'failed';
    public const PAYMENT_REFUNDED = 'refunded';

    protected $fillable = [
        'user_id',
        'order_number',
        'status',
        'payment_status',
        'payment_method',
        'currency',
        'subtotal',
        'delivery_fee',
        'total',
        'first_name',
        'last_name',
        'email',
        'phone',
        'delivery_method',
        'delivery_province',
        'delivery_district',
        'delivery_sector',
        'delivery_street',
        'delivery_instructions',
        'placed_at',
        'confirmed_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'delivery_fee' => 'decimal:2',
            'total' => 'decimal:2',
            'placed_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    protected static function booted(): void
    {
        static::creating(
            static function (ProductOrder $order): void {
                if (blank($order->order_number)) {
                    $order->order_number =
                        'RSP-'
                        .now()->format('Ymd')
                        .'-'
                        .Str::upper(Str::random(8));
                }

                if (blank($order->status)) {
                    $order->status =
                        self::STATUS_PENDING;
                }

                if (blank($order->payment_status)) {
                    $order->payment_status =
                        self::PAYMENT_PENDING;
                }

                $order->currency = strtoupper(
                    trim($order->currency ?: 'RWF')
                );

                $order->placed_at ??= now();
            }
        );
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'user_id'
        );
    }

    public function items(): HasMany
    {
        return $this->hasMany(
            ProductOrderItem::class
        );
    }

    public function canBeCancelled(): bool
    {
        return in_array(
            $this->status,
            [
                self::STATUS_PENDING,
                self::STATUS_CONFIRMED,
            ],
            true
        );
    }
}
