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
     * This must match the guard_name used in RoleSeeder.
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
     * The attributes that should be hidden for serialization.
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
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
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
     */
    public function isSeller(): bool
    {
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
     * Check whether the account is active.
     */
    public function isActive(): bool
    {
        return $this->status ===
            self::STATUS_ACTIVE;
    }

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
}
