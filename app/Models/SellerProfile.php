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

    protected function casts(): array
    {
        return [
            'status' => SellerProfileStatus::class,
            'approved_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SellerProfile $seller): void {
            $seller->public_id ??= (string) Str::uuid();
        });
    }

    public function members(): HasMany
    {
        return $this->hasMany(SellerMember::class);
    }

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

    public function applications(): HasMany
    {
        return $this->hasMany(SellerApplication::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(SellerDocument::class);
    }

    public function addresses(): MorphMany
    {
        return $this->morphMany(Address::class, 'addressable');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function suspendedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suspended_by');
    }

    public function isApproved(): bool
    {
        return $this->status === SellerProfileStatus::APPROVED;
    }
}