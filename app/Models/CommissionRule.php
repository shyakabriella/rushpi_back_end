<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

final class CommissionRule extends Model
{
    use HasFactory;
    use HasUlids;
    use SoftDeletes;

    /*
    |--------------------------------------------------------------------------
    | Commission scopes
    |--------------------------------------------------------------------------
    */

    public const SCOPE_GLOBAL = 'global';

    public const SCOPE_DEPARTMENT = 'department';

    public const SCOPE_CATEGORY = 'category';

    /*
    |--------------------------------------------------------------------------
    | Commission types
    |--------------------------------------------------------------------------
    */

    public const TYPE_PERCENTAGE = 'percentage';

    public const TYPE_FIXED = 'fixed';

    /**
     * Mass assignable fields.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'scope',
        'department_id',
        'category_id',

        'commission_type',
        'commission_value',

        'minimum_commission',
        'maximum_commission',

        'currency',
        'priority',

        'starts_at',
        'ends_at',

        'is_active',

        'created_by',
        'updated_by',
    ];

    /**
     * Attribute casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'department_id' => 'integer',

            'category_id' => 'integer',

            'commission_value' => 'decimal:4',

            'minimum_commission' => 'decimal:2',

            'maximum_commission' => 'decimal:2',

            'priority' => 'integer',

            'is_active' => 'boolean',

            'starts_at' => 'datetime',

            'ends_at' => 'datetime',

            'created_at' => 'datetime',

            'updated_at' => 'datetime',

            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Generate public ULID.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return [
            'public_id',
        ];
    }

    /**
     * Use public_id in API route binding.
     */
    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * Normalize values before saving.
     */
    protected static function booted(): void
    {
        static::saving(
            static function (
                CommissionRule $rule
            ): void {
                $rule->name = trim(
                    (string) $rule->name
                );

                $rule->scope = strtolower(
                    trim(
                        (string) $rule->scope
                    )
                );

                $rule->commission_type = strtolower(
                    trim(
                        (string)
                        $rule->commission_type
                    )
                );

                $rule->currency = strtoupper(
                    trim(
                        (string) (
                            $rule->currency
                            ?: 'RWF'
                        )
                    )
                );

                /*
                 * Global rules must not point to
                 * a department or category.
                 */
                if (
                    $rule->scope ===
                    self::SCOPE_GLOBAL
                ) {
                    $rule->department_id = null;

                    $rule->category_id = null;
                }

                /*
                 * Department rules only target
                 * a department.
                 */
                if (
                    $rule->scope ===
                    self::SCOPE_DEPARTMENT
                ) {
                    $rule->category_id = null;
                }

                /*
                 * Category rules only target
                 * a category.
                 */
                if (
                    $rule->scope ===
                    self::SCOPE_CATEGORY
                ) {
                    $rule->department_id = null;
                }

                /*
                 * Min/max commission limits only
                 * apply to percentage rules.
                 */
                if (
                    $rule->commission_type ===
                    self::TYPE_FIXED
                ) {
                    $rule->minimum_commission = null;

                    $rule->maximum_commission = null;
                }
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Department targeted by this rule.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(
            Department::class,
            'department_id'
        );
    }

    /**
     * Category targeted by this rule.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(
            Category::class,
            'category_id'
        );
    }

    /**
     * Administrator who created this rule.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    /**
     * Administrator who last updated this rule.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'updated_by'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Query scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Only enabled rules.
     */
    public function scopeActive(
        Builder $query
    ): Builder {
        return $query->where(
            'is_active',
            true
        );
    }

    /**
     * Rules that are effective right now.
     */
    public function scopeCurrentlyEffective(
        Builder $query
    ): Builder {
        $now = now();

        return $query
            ->where(
                static function (
                    Builder $dateQuery
                ) use ($now): void {
                    $dateQuery
                        ->whereNull(
                            'starts_at'
                        )
                        ->orWhere(
                            'starts_at',
                            '<=',
                            $now
                        );
                }
            )
            ->where(
                static function (
                    Builder $dateQuery
                ) use ($now): void {
                    $dateQuery
                        ->whereNull(
                            'ends_at'
                        )
                        ->orWhere(
                            'ends_at',
                            '>=',
                            $now
                        );
                }
            );
    }

    /**
     * Search commission rules.
     */
    public function scopeSearch(
        Builder $query,
        ?string $search
    ): Builder {
        $search = trim(
            (string) $search
        );

        if ($search === '') {
            return $query;
        }

        $escaped = addcslashes(
            $search,
            '\\%_'
        );

        $like = "%{$escaped}%";

        return $query->where(
            static function (
                Builder $searchQuery
            ) use ($like): void {
                $searchQuery
                    ->where(
                        'name',
                        'like',
                        $like
                    )
                    ->orWhere(
                        'scope',
                        'like',
                        $like
                    )
                    ->orWhere(
                        'commission_type',
                        'like',
                        $like
                    )
                    ->orWhereHas(
                        'department',
                        static function (
                            Builder $departmentQuery
                        ) use ($like): void {
                            $departmentQuery->where(
                                'name',
                                'like',
                                $like
                            );
                        }
                    )
                    ->orWhereHas(
                        'category',
                        static function (
                            Builder $categoryQuery
                        ) use ($like): void {
                            $categoryQuery->where(
                                'name',
                                'like',
                                $like
                            );
                        }
                    );
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | State helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether this rule is currently usable.
     */
    public function isCurrentlyEffective(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $now = now();

        if (
            $this->starts_at !== null
            && $this->starts_at->isAfter(
                $now
            )
        ) {
            return false;
        }

        if (
            $this->ends_at !== null
            && $this->ends_at->isBefore(
                $now
            )
        ) {
            return false;
        }

        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | Commission calculation
    |--------------------------------------------------------------------------
    */

    /**
     * Calculate RushPi commission for
     * a selling price.
     */
    public function calculateCommission(
        float $sellingPrice
    ): float {
        /*
         * Negative prices are not allowed.
         */
        $sellingPrice = max(
            0,
            $sellingPrice
        );

        /*
         * Fixed amount commission.
         */
        if (
            $this->commission_type ===
            self::TYPE_FIXED
        ) {
            return round(
                min(
                    $sellingPrice,
                    (float)
                    $this->commission_value
                ),
                2
            );
        }

        /*
         * Percentage commission.
         *
         * Example:
         *
         * 1,000,000 RWF × 6%
         * = 60,000 RWF
         */
        $commission =
            $sellingPrice
            * (
                (float)
                $this->commission_value
                / 100
            );

        /*
         * Apply minimum commission.
         */
        if (
            $this->minimum_commission
            !== null
        ) {
            $commission = max(
                $commission,
                (float)
                $this->minimum_commission
            );
        }

        /*
         * Apply maximum commission.
         */
        if (
            $this->maximum_commission
            !== null
        ) {
            $commission = min(
                $commission,
                (float)
                $this->maximum_commission
            );
        }

        /*
         * Commission should never exceed
         * the selling price.
         */
        return round(
            min(
                $sellingPrice,
                $commission
            ),
            2
        );
    }

    /**
     * Calculate amount remaining for seller.
     */
    public function calculateSellerAmount(
        float $sellingPrice
    ): float {
        $sellingPrice = max(
            0,
            $sellingPrice
        );

        $commission =
            $this->calculateCommission(
                $sellingPrice
            );

        return round(
            max(
                0,
                $sellingPrice
                - $commission
            ),
            2
        );
    }
}