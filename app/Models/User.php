<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
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
     * Spatie role guard.
     *
     * This must match the guard_name stored in the roles table.
     *
     * @var string
     */
    protected $guard_name = 'web';

    /**
     * Available RushPi user roles.
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
     * Roles permitted through public registration.
     *
     * Administrators must be created privately by the
     * system owner, seeder or another administrator.
     *
     * @var array<int, string>
     */
    public const PUBLIC_REGISTRATION_ROLES = [
        self::ROLE_SELLER,
        self::ROLE_DEALER,
        self::ROLE_COMMISSIONER,
    ];

    /**
     * Available account statuses.
     */
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_BLOCKED = 'blocked';

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
     * Attributes that may be mass assigned.
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
     * Attributes hidden during serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Attribute casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Return the user's effective role.
     *
     * The Spatie role is checked first. The users.role
     * column is retained for compatibility with existing APIs.
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
     * Determine whether the user has the supplied system role.
     */
    public function hasSystemRole(string $role): bool
    {
        return $this->role === $role
            || $this->hasRole($role);
    }

    /**
     * Determine whether the user is an administrator.
     */
    public function isAdmin(): bool
    {
        return $this->hasSystemRole(
            self::ROLE_ADMIN
        );
    }

    /**
     * Determine whether the user is a seller.
     */
    public function isSeller(): bool
    {
        return $this->hasSystemRole(
            self::ROLE_SELLER
        );
    }

    /**
     * Determine whether the user is a dealer.
     */
    public function isDealer(): bool
    {
        return $this->hasSystemRole(
            self::ROLE_DEALER
        );
    }

    /**
     * Determine whether the user is a commissioner.
     */
    public function isCommissioner(): bool
    {
        return $this->hasSystemRole(
            self::ROLE_COMMISSIONER
        );
    }

    /**
     * Determine whether the user is a marketplace partner.
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
     * Determine whether the account is active.
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Determine whether the account is inactive.
     */
    public function isInactive(): bool
    {
        return $this->status === self::STATUS_INACTIVE;
    }

    /**
     * Determine whether the account is blocked.
     */
    public function isBlocked(): bool
    {
        return $this->status === self::STATUS_BLOCKED;
    }
}
