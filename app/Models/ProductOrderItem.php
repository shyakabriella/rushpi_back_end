<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ProductOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_order_id',
        'seller_profile_id',
        'product_id',
        'product_variant_id',
        'product_name',
        'variant_name',
        'sku',
        'currency',
        'unit_price',
        'quantity',
        'line_total',
        'fulfilment_status',
        'confirmed_at',
        'shipped_at',
        'delivered_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'quantity' => 'integer',
            'line_total' => 'decimal:2',
            'confirmed_at' => 'datetime',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(
            ProductOrder::class,
            'product_order_id'
        );
    }

    public function sellerProfile(): BelongsTo
    {
        return $this->belongsTo(
            SellerProfile::class
        );
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(
            ProductVariant::class,
            'product_variant_id'
        );
    }
}
