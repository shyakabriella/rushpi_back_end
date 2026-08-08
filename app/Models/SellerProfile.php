<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SellerProfileStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class SellerProfile extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * Fields that may be assigned safely.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'legal_business_name',
        'trading_name',

        'logo',
        'cover_image',

        'business_type',

        'registration_number',
        'tax_identification_number',

        'business_email',
        'business_phone',
        'whatsapp',

        'country_code',
        'website',
        'description',

        'return_policy',
        'warranty_policy',

        'average_rating',
        'total_reviews',
        'total_orders',
        'completed_orders',
        'response_rate',
        'response_time',

        'status',

        'approved_at',
        'approved_by',

        'suspended_at',
        'suspended_by',
        'suspension_reason',
    ];

    /**
     * Seller profile attribute casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' =>
                SellerProfileStatus::class,

            'approved_at' =>
                'datetime',

            'suspended_at' =>
                'datetime',

            'average_rating' =>
                'decimal:2',

            'total_reviews' =>
                'integer',

            'total_orders' =>
                'integer',

            'completed_orders' =>
                'integer',

            'response_rate' =>
                'decimal:2',

            'response_time' =>
                'integer',
        ];
    }

    /**
     * Generate public identifier.
     */
    protected static function booted(): void
    {
        static::creating(
            function (
                SellerProfile $seller
            ): void {
                $seller->public_id ??=
                    (string) Str::uuid();
            }
        );
    }

    /**
     * Route model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * Seller membership records.
     */
    public function members(): HasMany
    {
        return $this->hasMany(
            SellerMember::class
        );
    }

    /**
     * Users belonging to seller.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'seller_members'
        )
            ->withPivot([
                'role',
                'status',
                'joined_at',
            ])
            ->withTimestamps();
    }

    /**
     * Seller verification applications.
     */
    public function applications(): HasMany
    {
        return $this->hasMany(
            SellerApplication::class
        );
    }

    /**
     * Seller verification documents.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(
            SellerDocument::class
        );
    }

    /**
     * Seller business addresses.
     */
    public function addresses(): MorphMany
    {
        return $this->morphMany(
            Address::class,
            'addressable'
        );
    }

    /**
     * Products owned by seller.
     */
    public function products(): HasMany
    {
        return $this
            ->hasMany(Product::class)
            ->orderByDesc(
                'created_at'
            );
    }

    /**
     * Administrator who approved seller.
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'approved_by'
        );
    }

    /**
     * Administrator who suspended seller.
     */
    public function suspendedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'suspended_by'
        );
    }

    /**
     * Determine whether seller is approved.
     */
    public function isApproved(): bool
    {
        return $this->status ===
            SellerProfileStatus::APPROVED;
    }
}