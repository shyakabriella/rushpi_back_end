<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SellerMemberRole;
use App\Enums\SellerMemberStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use HasRoles;
    use Notifiable;

    /**
     * Spatie permission guard.
     *
     * Must match guard_name used by the roles table.
     *
     * @var string
     */
    protected $guard_name = 'web';

    /*
    |--------------------------------------------------------------------------
    | Roles
    |--------------------------------------------------------------------------
    */

    public const ROLE_ADMIN = 'admin';

    public const ROLE_SELLER = 'seller';

    public const ROLE_DEALER = 'dealer';

    public const ROLE_COMMISSIONER = 'commissioner';

    /**
     * All supported RushPi roles.
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
     * Roles available during public registration.
     *
     * Administrators must be created through trusted
     * internal/admin workflows.
     *
     * @var array<int, string>
     */
    public const PUBLIC_REGISTRATION_ROLES = [
        self::ROLE_SELLER,
        self::ROLE_DEALER,
        self::ROLE_COMMISSIONER,
    ];

    /*
    |--------------------------------------------------------------------------
    | Account statuses
    |--------------------------------------------------------------------------
    */

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_BLOCKED = 'blocked';

    /**
     * Supported account statuses.
     *
     * @var array<int, string>
     */
    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
        self::STATUS_BLOCKED,
    ];

    /*
    |--------------------------------------------------------------------------
    | Mass assignment
    |--------------------------------------------------------------------------
    */

    /**
     * Attributes that may be mass assigned.
     *
     * Extended personal information belongs to
     * the user_profiles table.
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

        /*
         * Kept temporarily for compatibility
         * with existing RushPi data/code.
         *
         * New profile functionality should use
         * user_profiles.avatar/address.
         */
        'avatar',
        'address',
    ];

    /*
    |--------------------------------------------------------------------------
    | Hidden attributes
    |--------------------------------------------------------------------------
    */

    /**
     * Attributes hidden from serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /*
    |--------------------------------------------------------------------------
    | Casts
    |--------------------------------------------------------------------------
    */

    /**
     * User attribute casts.
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

    /*
    |--------------------------------------------------------------------------
    | Personal profile
    |--------------------------------------------------------------------------
    */

    /**
     * Personal profile belonging to this user.
     *
     * One user = one personal profile.
     *
     * This is NOT the seller/store profile.
     */
    public function profile(): HasOne
    {
        return $this->hasOne(
            UserProfile::class,
            'user_id'
        );
    }

    /**
     * Create the user's personal profile when
     * it does not already exist.
     *
     * Useful after registration or when opening
     * the profile page for the first time.
     */
    public function ensureProfile(): UserProfile
    {
        return $this
            ->profile()
            ->firstOrCreate([
                'user_id' => $this->id,
            ]);
    }

    /**
     * Determine whether this user already
     * has a personal profile.
     */
    public function hasProfile(): bool
    {
        return $this
            ->profile()
            ->exists();
    }

    /*
    |--------------------------------------------------------------------------
    | Seller membership relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Direct seller membership records.
     *
     * seller_members contains:
     *
     * user_id
     * seller_profile_id
     * role
     * status
     * invited_at
     * joined_at
     * removed_at
     */
    public function sellerMemberships(): HasMany
    {
        return $this->hasMany(
            SellerMember::class,
            'user_id'
        );
    }

    /**
     * All seller businesses this user belongs to.
     *
     * A user may belong to multiple stores/businesses.
     */
    public function sellerProfiles(): BelongsToMany
    {
        return $this
            ->belongsToMany(
                SellerProfile::class,
                'seller_members',
                'user_id',
                'seller_profile_id'
            )
            ->withPivot([
                'role',
                'status',
                'invited_at',
                'joined_at',
                'removed_at',
            ])
            ->withTimestamps();
    }

    /**
     * Seller profiles actively owned by this user.
     */
    public function ownedSellerProfiles(): BelongsToMany
    {
        return $this
            ->belongsToMany(
                SellerProfile::class,
                'seller_members',
                'user_id',
                'seller_profile_id'
            )
            ->wherePivot(
                'role',
                SellerMemberRole::OWNER->value
            )
            ->wherePivot(
                'status',
                SellerMemberStatus::ACTIVE->value
            )
            ->withPivot([
                'role',
                'status',
                'invited_at',
                'joined_at',
                'removed_at',
            ])
            ->withTimestamps();
    }

    /**
     * Seller profiles where the user currently has
     * an active membership.
     */
    public function activeSellerProfiles(): BelongsToMany
    {
        return $this
            ->belongsToMany(
                SellerProfile::class,
                'seller_members',
                'user_id',
                'seller_profile_id'
            )
            ->wherePivot(
                'status',
                SellerMemberStatus::ACTIVE->value
            )
            ->withPivot([
                'role',
                'status',
                'invited_at',
                'joined_at',
                'removed_at',
            ])
            ->withTimestamps();
    }

    /*
    |--------------------------------------------------------------------------
    | Seller membership helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether the user belongs
     * to the supplied seller business.
     *
     * Used by:
     *
     * SellerProfileController::show()
     */
    public function belongsToSeller(
        SellerProfile $sellerProfile
    ): bool {
        return $this
            ->sellerMemberships()
            ->where(
                'seller_profile_id',
                $sellerProfile->getKey()
            )
            ->where(
                'status',
                SellerMemberStatus::ACTIVE->value
            )
            ->exists();
    }

    /**
     * Determine whether the user owns
     * the supplied seller business.
     *
     * Used by:
     *
     * SellerProfileController::update()
     */
    public function ownsSeller(
        SellerProfile $sellerProfile
    ): bool {
        return $this
            ->sellerMemberships()
            ->where(
                'seller_profile_id',
                $sellerProfile->getKey()
            )
            ->where(
                'role',
                SellerMemberRole::OWNER->value
            )
            ->where(
                'status',
                SellerMemberStatus::ACTIVE->value
            )
            ->exists();
    }

    /**
     * Return the user's membership record
     * for a specific seller business.
     */
    public function sellerMembership(
        SellerProfile $sellerProfile
    ): ?SellerMember {
        return $this
            ->sellerMemberships()
            ->where(
                'seller_profile_id',
                $sellerProfile->getKey()
            )
            ->first();
    }

    /**
     * Determine whether the user can manage
     * this seller business.
     *
     * Administrators can manage all businesses.
     * Seller owners can manage their own businesses.
     */
    public function canManageSeller(
        SellerProfile $sellerProfile
    ): bool {
        if ($this->isAdmin()) {
            return true;
        }

        return $this->ownsSeller(
            $sellerProfile
        );
    }

    /**
     * Determine whether the user owns
     * at least one seller business.
     */
    public function hasSellerProfile(): bool
    {
        return $this
            ->ownedSellerProfiles()
            ->exists();
    }

    /**
     * Return the primary seller business.
     *
     * Useful for the seller dashboard when the
     * logged-in seller currently manages one store.
     */
    public function primarySellerProfile(): ?SellerProfile
    {
        return $this
            ->ownedSellerProfiles()
            ->orderByDesc(
                'seller_profiles.created_at'
            )
            ->first();
    }

    /*
    |--------------------------------------------------------------------------
    | Roles
    |--------------------------------------------------------------------------
    */

    /**
     * Return the user's effective system role.
     *
     * Spatie role is checked first.
     * users.role remains available for compatibility.
     */
    public function effectiveRole(): ?string
    {
        $spatieRole = $this
            ->getRoleNames()
            ->first();

        if (
            is_string($spatieRole)
            &&
            $spatieRole !== ''
        ) {
            return $spatieRole;
        }

        if (
            is_string($this->role)
            &&
            $this->role !== ''
        ) {
            return $this->role;
        }

        return null;
    }

    /**
     * Check a system role through either
     * users.role or Spatie roles.
     */
    public function hasSystemRole(
        string $role
    ): bool {
        return $this->role === $role
            ||
            $this->hasRole($role);
    }

    /**
     * Determine whether user is admin.
     */
    public function isAdmin(): bool
    {
        return $this->hasSystemRole(
            self::ROLE_ADMIN
        );
    }

    /**
     * Determine whether user is seller.
     */
    public function isSeller(): bool
    {
        return $this->hasSystemRole(
            self::ROLE_SELLER
        );
    }

    /**
     * Determine whether user is dealer.
     */
    public function isDealer(): bool
    {
        return $this->hasSystemRole(
            self::ROLE_DEALER
        );
    }

    /**
     * Determine whether user is commissioner.
     */
    public function isCommissioner(): bool
    {
        return $this->hasSystemRole(
            self::ROLE_COMMISSIONER
        );
    }

    /**
     * Determine whether this user is one of
     * RushPi's marketplace partner roles.
     */
    public function isMarketplacePartner(): bool
    {
        $role =
            $this->effectiveRole();

        if ($role === null) {
            return false;
        }

        return in_array(
            $role,
            self::PUBLIC_REGISTRATION_ROLES,
            true
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Account status
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether account is active.
     */
    public function isActive(): bool
    {
        return $this->status ===
            self::STATUS_ACTIVE;
    }

    /**
     * Determine whether account is inactive.
     */
    public function isInactive(): bool
    {
        return $this->status ===
            self::STATUS_INACTIVE;
    }

    /**
     * Determine whether account is blocked.
     */
    public function isBlocked(): bool
    {
        return $this->status ===
            self::STATUS_BLOCKED;
    }

    /**
     * Determine whether user may access
     * protected RushPi functionality.
     */
    public function canAccessSystem(): bool
    {
        return $this->isActive()
            &&
            ! $this->isBlocked();
    }
}