<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

final class Department extends Model
{
    use HasFactory;
    use HasUlids;
    use SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'image_path',
        'is_active',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' =>
                'boolean',

            'sort_order' =>
                'integer',

            'created_at' =>
                'datetime',

            'updated_at' =>
                'datetime',

            'deleted_at' =>
                'datetime',
        ];
    }

    public function uniqueIds(): array
    {
        return [
            'public_id',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    protected static function booted(): void
    {
        static::saving(
            static function (
                Department $department
            ): void {
                $department->name =
                    trim(
                        (string)
                        $department->name
                    );

                $department->slug =
                    Str::slug(
                        trim(
                            (string) (
                                $department->slug
                                ?: $department->name
                            )
                        )
                    );

                if (
                    $department->description
                    !== null
                ) {
                    $description =
                        trim(
                            (string)
                            $department
                                ->description
                        );

                    $department->description =
                        $description !== ''
                            ? $description
                            : null;
                }

                if (
                    $department->image_path
                    !== null
                ) {
                    $imagePath =
                        trim(
                            (string)
                            $department
                                ->image_path
                        );

                    $department->image_path =
                        $imagePath !== ''
                            ? $imagePath
                            : null;
                }
            }
        );
    }

    public function categories(): BelongsToMany
    {
        return $this
            ->belongsToMany(
                Category::class,
                'department_category',
                'department_id',
                'category_id'
            )
            ->using(
                DepartmentCategory::class
            )
            ->withPivot([
                'id',
                'sort_order',
                'is_featured',
                'is_active',
            ])
            ->withTimestamps()
            ->orderByPivot(
                'sort_order'
            )
            ->orderBy(
                'categories.name'
            );
    }

    public function activeCategories(): BelongsToMany
    {
        return $this
            ->categories()
            ->wherePivot(
                'is_active',
                true
            )
            ->where(
                'categories.is_active',
                true
            );
    }

    public function commissionRules(): HasMany
    {
        return $this->hasMany(
            CommissionRule::class,
            'department_id'
        );
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'updated_by'
        );
    }

    public function scopeActive(
        Builder $query
    ): Builder {
        return $query->where(
            'is_active',
            true
        );
    }

    public function scopeOrdered(
        Builder $query
    ): Builder {
        return $query
            ->orderBy(
                'sort_order'
            )
            ->orderBy(
                'name'
            )
            ->orderBy(
                'id'
            );
    }

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
                        'slug',
                        'like',
                        $like
                    )
                    ->orWhere(
                        'description',
                        'like',
                        $like
                    );
            }
        );
    }
}