<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;

    /**
     * Available user roles.
     */
    public const ROLE_ADMIN =
        'admin';

    public const ROLE_CUSTOMER =
        'customer';

    /**
     * Available account statuses.
     */
    public const STATUS_ACTIVE =
        'active';

    public const STATUS_INACTIVE =
        'inactive';

    public const STATUS_BLOCKED =
        'blocked';

    /**
     * Available seller membership statuses.
     */
    public const SELLER_MEMBER_ACTIVE =
        'active';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'role',
        'status',
        'avatar',
        'address',
    ];

    /**
     * The attributes hidden during serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' =>
                'datetime',

            'password' =>
                'hashed',
        ];
    }

    /**
     * Seller businesses to which this user belongs.
     */
    public function sellerProfiles(): BelongsToMany
    {
        return $this->belongsToMany(
            SellerProfile::class,
            'seller_members',
            'user_id',
            'seller_profile_id'
        )->withPivot([
            'id',
            'role',
            'status',
            'joined_at',
        ]);
    }

    /**
     * Determine whether the user is an active member
     * of the supplied seller business.
     */
    public function belongsToSeller(
        SellerProfile $sellerProfile
    ): bool {
        if (
            !$this->exists
            || !$sellerProfile->exists
        ) {
            return false;
        }

        return $this
            ->sellerProfiles()
            ->whereKey(
                $sellerProfile->getKey()
            )
            ->wherePivot(
                'status',
                self::SELLER_MEMBER_ACTIVE
            )
            ->exists();
    }

    /**
     * Determine whether the user owns
     * the supplied seller business.
     */
    public function ownsSeller(
        SellerProfile $sellerProfile
    ): bool {
        if (
            !$this->exists
            || !$sellerProfile->exists
        ) {
            return false;
        }

        return $this
            ->sellerProfiles()
            ->whereKey(
                $sellerProfile->getKey()
            )
            ->wherePivot(
                'role',
                'owner'
            )
            ->wherePivot(
                'status',
                self::SELLER_MEMBER_ACTIVE
            )
            ->exists();
    }

    /**
     * Check whether the user is an administrator.
     */
    public function isAdmin(): bool
    {
        return $this->role ===
            self::ROLE_ADMIN;
    }

    /**
     * Check whether the user is a customer.
     */
    public function isCustomer(): bool
    {
        return $this->role ===
            self::ROLE_CUSTOMER;
    }

    /**
     * Check whether the account is active.
     */
    public function isActive(): bool
    {
        return $this->status ===
            self::STATUS_ACTIVE;
    }
}
