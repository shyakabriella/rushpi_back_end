<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Category extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * Fields that can be assigned safely.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'description',
        'image_path',
        'is_active',
        'sort_order',
    ];

    /**
     * Model attribute casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Generate the public ID and slug automatically.
     */
    protected static function booted(): void
    {
        static::creating(function (Category $category): void {
            if (blank($category->public_id)) {
                $category->public_id = (string) Str::ulid();
            }

            if (blank($category->slug)) {
                $category->slug = static::generateUniqueSlug(
                    $category->name
                );
            }
        });

        static::updating(function (Category $category): void {
            if (
                $category->isDirty('name')
                && !$category->isDirty('slug')
            ) {
                $category->slug = static::generateUniqueSlug(
                    $category->name,
                    $category->id
                );
            }
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
     * Parent category.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(
            related: self::class,
            foreignKey: 'parent_id'
        );
    }

    /**
     * Child categories.
     */
    public function children(): HasMany
    {
        return $this->hasMany(
            related: self::class,
            foreignKey: 'parent_id'
        )->orderBy('sort_order')
            ->orderBy('name');
    }

    /**
     * Products assigned to this category.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Limit results to active categories.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Limit results to root categories.
     */
    public function scopeRoot(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    /**
     * Order categories for display.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    /**
     * Generate a unique category slug.
     */
    private static function generateUniqueSlug(
        string $name,
        ?int $ignoreId = null
    ): string {
        $baseSlug = Str::slug($name);

        if ($baseSlug === '') {
            $baseSlug = 'category';
        }

        $slug = $baseSlug;
        $number = 2;

        while (
            static::query()
                ->withTrashed()
                ->when(
                    $ignoreId !== null,
                    fn (Builder $query): Builder => $query->where(
                        'id',
                        '!=',
                        $ignoreId
                    )
                )
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $baseSlug.'-'.$number;
            $number++;
        }

        return $slug;
    }
}