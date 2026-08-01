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
        'registration_number',
        'tax_identification_number',
        'business_email',
        'business_phone',
        'website',
        'description',
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
            'status' => SellerProfileStatus::class,
            'approved_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    /**
     * Generate the public identifier automatically.
     */
    protected static function booted(): void
    {
        static::creating(function (SellerProfile $seller): void {
            $seller->public_id ??= (string) Str::uuid();
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
     * Seller membership records.
     */
    public function members(): HasMany
    {
        return $this->hasMany(SellerMember::class);
    }

    /**
     * Users belonging to this seller business.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'seller_members'
        )->withPivot([
            'role',
            'status',
            'joined_at',
        ])->withTimestamps();
    }

    /**
     * Seller verification applications.
     */
    public function applications(): HasMany
    {
        return $this->hasMany(SellerApplication::class);
    }

    /**
     * Seller verification documents.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(SellerDocument::class);
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
     * Products owned by this seller business.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class)
            ->orderByDesc('created_at');
    }

    /**
     * Administrator who approved this seller.
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'approved_by'
        );
    }

    /**
     * Administrator who suspended this seller.
     */
    public function suspendedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'suspended_by'
        );
    }

    /**
     * Determine whether the seller is approved.
     */
    public function isApproved(): bool
    {
        return $this->status === SellerProfileStatus::APPROVED;
    }
}