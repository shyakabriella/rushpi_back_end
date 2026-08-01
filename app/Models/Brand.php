<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Brand extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * Fields that may be assigned through create() or update().
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'logo_path',
        'website_url',
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
        static::creating(function (Brand $brand): void {
            if (blank($brand->public_id)) {
                $brand->public_id = (string) Str::ulid();
            }

            if (blank($brand->slug)) {
                $brand->slug = static::generateUniqueSlug(
                    $brand->name
                );
            }
        });

        static::updating(function (Brand $brand): void {
            if (
                $brand->isDirty('name')
                && ! $brand->isDirty('slug')
            ) {
                $brand->slug = static::generateUniqueSlug(
                    $brand->name,
                    $brand->id
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
     * Products belonging to this brand.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Limit results to active brands.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Order brands for display.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    /**
     * Search brands by name or description.
     */
    public function scopeSearch(
        Builder $query,
        ?string $search
    ): Builder {
        $search = trim((string) $search);

        if ($search === '') {
            return $query;
        }

        return $query->where(
            function (Builder $brandQuery) use ($search): void {
                $brandQuery
                    ->where('name', 'like', '%'.$search.'%')
                    ->orWhere(
                        'description',
                        'like',
                        '%'.$search.'%'
                    );
            }
        );
    }

    /**
     * Generate a unique brand slug.
     */
    private static function generateUniqueSlug(
        string $name,
        ?int $ignoreId = null
    ): string {
        $baseSlug = Str::slug($name);

        if ($baseSlug === '') {
            $baseSlug = 'brand';
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