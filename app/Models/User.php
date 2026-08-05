<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
<<<<<<< HEAD
    use HasRoles;
=======
>>>>>>> ddc347f4d98c1bde70cb3726989af3ead08b7d92
    use Notifiable;

    /**
     * Spatie role guard.
     *
     * This must match the guard_name used in RoleSeeder.
     *
     * @var string
     */
    protected $guard_name = 'web';

    /**
     * Available RushPi user roles.
     */
<<<<<<< HEAD
    public const ROLE_ADMIN = 'admin';
    public const ROLE_SELLER = 'seller';
    public const ROLE_DEALER = 'dealer';
    public const ROLE_COMMISSIONER = 'commissioner';

    /**
     * All supported system roles.
     *
     * @var array<int, string>
     */
    public const ROLES = [
        self::ROLE_ADMIN,
        self::ROLE_SELLER,
        self::ROLE_DEALER,
        self::ROLE_COMMISSIONER,
    ];

    /**
     * Roles allowed through public registration.
     *
     * Administrators must be created by the system owner,
     * database seeder or another authorized administrator.
     *
     * @var array<int, string>
     */
    public const PUBLIC_REGISTRATION_ROLES = [
        self::ROLE_SELLER,
        self::ROLE_DEALER,
        self::ROLE_COMMISSIONER,
    ];
=======
    public const ROLE_ADMIN =
        'admin';

    public const ROLE_CUSTOMER =
        'customer';
>>>>>>> ddc347f4d98c1bde70cb3726989af3ead08b7d92

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
     * All supported account statuses.
     *
     * @var array<int, string>
     */
    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
        self::STATUS_BLOCKED,
    ];

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
<<<<<<< HEAD
     * Get the user's effective role.
     *
     * Spatie roles are checked first. The users.role column is
     * retained as a fallback for existing APIs and frontend responses.
     */
    public function effectiveRole(): ?string
    {
        $spatieRole = $this
            ->getRoleNames()
            ->first();

        if (
            is_string($spatieRole)
            && $spatieRole !== ''
        ) {
            return $spatieRole;
        }

        if (
            is_string($this->role)
            && $this->role !== ''
        ) {
            return $this->role;
        }

        return null;
    }

    /**
     * Check whether the user has a specific RushPi role.
     */
    public function hasSystemRole(string $role): bool
    {
        return $this->role === $role
            || $this->hasRole($role);
    }

    /**
     * Check whether the user is a system administrator.
     */
    public function isAdmin(): bool
    {
        return $this->hasSystemRole(
            self::ROLE_ADMIN
        );
    }

    /**
     * Check whether the user is a seller or shop owner.
=======
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
>>>>>>> ddc347f4d98c1bde70cb3726989af3ead08b7d92
     */
    public function isSeller(): bool
    {
<<<<<<< HEAD
        return $this->hasSystemRole(
            self::ROLE_SELLER
        );
    }

    /**
     * Check whether the user is a deal partner.
     */
    public function isDealer(): bool
    {
        return $this->hasSystemRole(
            self::ROLE_DEALER
        );
    }

    /**
     * Check whether the user is a commission agent.
     */
    public function isCommissioner(): bool
    {
        return $this->hasSystemRole(
            self::ROLE_COMMISSIONER
        );
    }

    /**
     * Check whether the user is one of the public business roles.
     */
    public function isMarketplacePartner(): bool
    {
        return in_array(
            $this->effectiveRole(),
            self::PUBLIC_REGISTRATION_ROLES,
            true
        );
    }

    /**
=======
        return $this->role ===
            self::ROLE_CUSTOMER;
    }

    /**
>>>>>>> ddc347f4d98c1bde70cb3726989af3ead08b7d92
     * Check whether the account is active.
     */
    public function isActive(): bool
    {
        return $this->status ===
            self::STATUS_ACTIVE;
    }
<<<<<<< HEAD

    /**
     * Check whether the account is inactive.
     */
    public function isInactive(): bool
    {
        return $this->status ===
            self::STATUS_INACTIVE;
    }

    /**
     * Check whether the account is blocked.
     */
    public function isBlocked(): bool
    {
        return $this->status ===
            self::STATUS_BLOCKED;
    }
=======
>>>>>>> ddc347f4d98c1bde70cb3726989af3ead08b7d92
}
