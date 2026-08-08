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
     * Brand remains global across the marketplace.
     * Products connect a brand to their category/department.
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
     * Attribute casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Prepare brand attributes before creating/updating.
     */
    protected static function booted(): void
    {
        /*
         * Before creating a brand:
         *
         * - normalize the name
         * - generate public ULID
         * - generate/normalize slug
         */
        static::creating(
            function (Brand $brand): void {
                $brand->name = trim(
                    (string) $brand->name
                );

                if (blank($brand->public_id)) {
                    $brand->public_id =
                        (string) Str::ulid();
                }

                /*
                 * If no slug was supplied,
                 * generate one from the brand name.
                 */
                if (blank($brand->slug)) {
                    $brand->slug =
                        static::generateUniqueSlug(
                            $brand->name
                        );
                } else {
                    /*
                     * Normalize manually supplied slug.
                     */
                    $brand->slug = Str::slug(
                        (string) $brand->slug
                    );
                }

                if (
                    is_string($brand->description)
                ) {
                    $brand->description =
                        static::normalizeNullableString(
                            $brand->description
                        );
                }

                if (
                    is_string($brand->logo_path)
                ) {
                    $brand->logo_path =
                        static::normalizeNullableString(
                            $brand->logo_path
                        );
                }

                if (
                    is_string($brand->website_url)
                ) {
                    $brand->website_url =
                        static::normalizeNullableString(
                            $brand->website_url
                        );
                }
            }
        );

        /*
         * Before updating a brand.
         */
        static::updating(
            function (Brand $brand): void {
                if ($brand->isDirty('name')) {
                    $brand->name = trim(
                        (string) $brand->name
                    );
                }

                /*
                 * If a custom slug was explicitly changed,
                 * normalize it.
                 */
                if (
                    $brand->isDirty('slug')
                    && ! blank($brand->slug)
                ) {
                    $brand->slug = Str::slug(
                        (string) $brand->slug
                    );
                }

                /*
                 * When the brand name changes and the
                 * slug was not explicitly changed,
                 * generate a new unique slug.
                 */
                if (
                    $brand->isDirty('name')
                    && ! $brand->isDirty('slug')
                ) {
                    $brand->slug =
                        static::generateUniqueSlug(
                            $brand->name,
                            (int) $brand->getKey()
                        );
                }

                if (
                    $brand->isDirty('description')
                    && is_string(
                        $brand->description
                    )
                ) {
                    $brand->description =
                        static::normalizeNullableString(
                            $brand->description
                        );
                }

                if (
                    $brand->isDirty('logo_path')
                    && is_string(
                        $brand->logo_path
                    )
                ) {
                    $brand->logo_path =
                        static::normalizeNullableString(
                            $brand->logo_path
                        );
                }

                if (
                    $brand->isDirty('website_url')
                    && is_string(
                        $brand->website_url
                    )
                ) {
                    $brand->website_url =
                        static::normalizeNullableString(
                            $brand->website_url
                        );
                }
            }
        );
    }

    /**
     * Use public_id instead of the internal numeric ID
     * for route model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Products using this brand.
     *
     * Example:
     *
     * Nike
     * ├── Air Force 1
     * ├── Air Max
     * └── Nike T-Shirt
     *
     * Samsung
     * ├── Galaxy Phone
     * ├── Television
     * └── Refrigerator
     */
    public function products(): HasMany
    {
        return $this->hasMany(
            Product::class,
            'brand_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Only active brands.
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
     * Only inactive brands.
     */
    public function scopeInactive(
        Builder $query
    ): Builder {
        return $query->where(
            'is_active',
            false
        );
    }

    /**
     * Default marketplace ordering.
     */
    public function scopeOrdered(
        Builder $query
    ): Builder {
        return $query
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    /**
     * Search brands.
     *
     * Searches:
     * - name
     * - slug
     * - description
     * - website
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

        /*
         * Escape SQL LIKE wildcard characters supplied
         * by the user.
         */
        $escapedSearch = addcslashes(
            $search,
            '\\%_'
        );

        $like = '%'.$escapedSearch.'%';

        return $query->where(
            function (
                Builder $brandQuery
            ) use ($like): void {
                $brandQuery
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
                    )
                    ->orWhere(
                        'website_url',
                        'like',
                        $like
                    );
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Generate a unique brand slug.
     *
     * Example:
     *
     * Nike        => nike
     * Nike        => nike-2
     * Samsung     => samsung
     */
    private static function generateUniqueSlug(
        string $name,
        ?int $ignoreId = null
    ): string {
        $baseSlug = Str::slug(
            trim($name)
        );

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
                    function (
                        Builder $query
                    ) use ($ignoreId): Builder {
                        return $query->where(
                            'id',
                            '!=',
                            $ignoreId
                        );
                    }
                )
                ->where(
                    'slug',
                    $slug
                )
                ->exists()
        ) {
            $slug =
                $baseSlug
                . '-'
                . $number;

            $number++;
        }

        return $slug;
    }

    /**
     * Convert empty strings to null and trim
     * non-empty strings.
     */
    private static function normalizeNullableString(
        string $value
    ): ?string {
        $value = trim($value);

        return $value === ''
            ? null
            : $value;
    }
}