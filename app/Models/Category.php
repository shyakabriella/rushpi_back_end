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
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Category extends Model
{
    use HasFactory;
    use HasUlids;
    use SoftDeletes;

    /**
     * Attributes that may be mass assigned.
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
            'parent_id' => 'integer',

            'is_active' => 'boolean',

            'sort_order' => 'integer',

            'created_at' => 'datetime',

            'updated_at' => 'datetime',

            'deleted_at' => 'datetime',
        ];
    }

    /**
     * Generate a ULID for the public identifier.
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
     * Use the public identifier for implicit route binding.
     */
    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * Normalize category values before persistence.
     */
    protected static function booted(): void
    {
        static::saving(
            static function (Category $category): void {
                $category->name = trim(
                    (string) $category->name
                );

                if (
                    $category->slug === null
                    || trim((string) $category->slug) === ''
                ) {
                    $category->slug = Str::slug(
                        $category->name
                    );
                } else {
                    $category->slug = Str::slug(
                        trim((string) $category->slug)
                    );
                }

                if ($category->description !== null) {
                    $description = trim(
                        (string) $category->description
                    );

                    $category->description =
                        $description !== ''
                            ? $description
                            : null;
                }

                if ($category->image_path !== null) {
                    $imagePath = trim(
                        (string) $category->image_path
                    );

                    $category->image_path =
                        $imagePath !== ''
                            ? $imagePath
                            : null;
                }
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Category hierarchy
    |--------------------------------------------------------------------------
    */

    /**
     * Parent category.
     *
     * @return BelongsTo<Category, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(
            self::class,
            'parent_id'
        );
    }

    /**
     * Direct child categories.
     *
     * @return HasMany<Category, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(
            self::class,
            'parent_id'
        );
    }

    /**
     * Active direct child categories.
     *
     * @return HasMany<Category, $this>
     */
    public function activeChildren(): HasMany
    {
        return $this
            ->children()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id');
    }

    /*
    |--------------------------------------------------------------------------
    | Products
    |--------------------------------------------------------------------------
    */

    /**
     * Products assigned to this category.
     *
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(
            Product::class,
            'category_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Specification taxonomy
    |--------------------------------------------------------------------------
    */

    /**
     * All direct specification assignments for this category.
     *
     * @return HasMany<CategorySpecification, $this>
     */
    public function specificationAssignments(): HasMany
    {
        return $this->hasMany(
            CategorySpecification::class,
            'category_id'
        );
    }

    /**
     * Active direct specification assignments whose definitions are active.
     *
     * @return HasMany<CategorySpecification, $this>
     */
    public function availableSpecificationAssignments(): HasMany
    {
        return $this
            ->specificationAssignments()
            ->where(
                'category_specifications.is_active',
                true
            )
            ->whereHas(
                'specificationDefinition',
                static function (
                    Builder $query
                ): void {
                    $query->where(
                        'is_active',
                        true
                    );
                }
            )
            ->orderBy(
                'category_specifications.sort_order'
            )
            ->orderBy(
                'category_specifications.id'
            );
    }

    /**
     * Required direct specification assignments.
     *
     * @return HasMany<CategorySpecification, $this>
     */
    public function requiredSpecificationAssignments(): HasMany
    {
        return $this
            ->availableSpecificationAssignments()
            ->where(
                'category_specifications.is_required',
                true
            );
    }

    /**
     * Filterable direct specification assignments.
     *
     * @return HasMany<CategorySpecification, $this>
     */
    public function filterableSpecificationAssignments(): HasMany
    {
        return $this
            ->availableSpecificationAssignments()
            ->where(
                'category_specifications.is_filterable',
                true
            );
    }

    /**
     * Direct specification assignments used to build variants.
     *
     * @return HasMany<CategorySpecification, $this>
     */
    public function variantSpecificationAssignments(): HasMany
    {
        return $this
            ->availableSpecificationAssignments()
            ->where(
                'category_specifications.is_variant_attribute',
                true
            );
    }

    /**
     * Reusable definitions assigned directly to this category.
     *
     * Prefer specificationAssignments() when category-level casts and
     * helper methods are required.
     *
     * @return BelongsToMany<SpecificationDefinition, $this>
     */
    public function specificationDefinitions(): BelongsToMany
    {
        return $this
            ->belongsToMany(
                SpecificationDefinition::class,
                'category_specifications',
                'category_id',
                'specification_definition_id'
            )
            ->withPivot([
                'public_id',
                'label',
                'help_text',
                'is_required',
                'is_filterable',
                'is_variant_attribute',
                'is_active',
                'validation_rules',
                'options',
                'default_value',
                'sort_order',
                'created_by',
                'updated_by',
            ])
            ->withTimestamps();
    }

    /*
    |--------------------------------------------------------------------------
    | Audit relationships
    |--------------------------------------------------------------------------
    */

    /**
     * User who created the category.
     *
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    /**
     * User who last updated the category.
     *
     * @return BelongsTo<User, $this>
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
     * Restrict results to active categories.
     *
     * @param Builder<Category> $query
     *
     * @return Builder<Category>
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
     * Restrict results to root categories.
     *
     * @param Builder<Category> $query
     *
     * @return Builder<Category>
     */
    public function scopeRoots(
        Builder $query
    ): Builder {
        return $query->whereNull(
            'parent_id'
        );
    }

    /**
     * Restrict results to direct children of a category.
     *
     * @param Builder<Category> $query
     *
     * @return Builder<Category>
     */
    public function scopeForParent(
        Builder $query,
        ?int $parentId
    ): Builder {
        if ($parentId === null) {
            return $query->whereNull(
                'parent_id'
            );
        }

        return $query->where(
            'parent_id',
            $parentId
        );
    }

    /**
     * Apply standard category ordering.
     *
     * @param Builder<Category> $query
     *
     * @return Builder<Category>
     */
    public function scopeOrdered(
        Builder $query
    ): Builder {
        return $query
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id');
    }

    /**
     * Search categories by name, slug or description.
     *
     * @param Builder<Category> $query
     *
     * @return Builder<Category>
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

        $escapedSearch = addcslashes(
            $search,
            '\\%_'
        );

        $like = "%{$escapedSearch}%";

        return $query->where(
            static function (
                Builder $searchQuery
            ) use ($like): void {
                $searchQuery
                    ->where('name', 'like', $like)
                    ->orWhere('slug', 'like', $like)
                    ->orWhere(
                        'description',
                        'like',
                        $like
                    );
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Hierarchy helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether this is a root category.
     */
    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    /**
     * Determine whether the category has direct children.
     */
    public function hasChildren(): bool
    {
        if ($this->relationLoaded('children')) {
            return $this->children->isNotEmpty();
        }

        return $this
            ->children()
            ->exists();
    }

    /**
     * Determine whether this category contains products.
     */
    public function hasProducts(): bool
    {
        if ($this->relationLoaded('products')) {
            return $this->products->isNotEmpty();
        }

        return $this
            ->products()
            ->exists();
    }

    /**
     * Determine whether assigning the supplied parent would create a cycle.
     */
    public function wouldCreateParentCycle(
        ?Category $candidateParent
    ): bool {
        if ($candidateParent === null) {
            return false;
        }

        if (
            $this->exists
            && $candidateParent->is($this)
        ) {
            return true;
        }

        $visited = [];
        $current = $candidateParent;

        while ($current !== null) {
            if (isset($visited[$current->getKey()])) {
                return true;
            }

            $visited[$current->getKey()] = true;

            if (
                $this->exists
                && $current->getKey() === $this->getKey()
            ) {
                return true;
            }

            $current = $current->parent;
        }

        return false;
    }

    /**
     * Return the category hierarchy from the root to this category.
     *
     * @return Collection<int, Category>
     */
    public function lineage(): Collection
    {
        $lineage = collect();
        $visited = [];

        $current = $this;

        while ($current !== null) {
            $key = $current->getKey();

            if (
                $key !== null
                && isset($visited[$key])
            ) {
                break;
            }

            if ($key !== null) {
                $visited[$key] = true;
            }

            $lineage->prepend($current);

            $current = $current->relationLoaded('parent')
                ? $current->parent
                : $current
                    ->parent()
                    ->first();
        }

        return $lineage->values();
    }

    /*
    |--------------------------------------------------------------------------
    | Effective specification inheritance
    |--------------------------------------------------------------------------
    */

    /**
     * Return effective active specifications for this category.
     *
     * Parent-category specifications are inherited. When a child category
     * assigns the same specification definition, the child assignment
     * overrides the inherited parent assignment.
     *
     * Example:
     *
     * Electronics
     *     Warranty: optional
     *
     * Laptops
     *     Warranty: required
     *
     * The Laptop category receives the required child configuration.
     *
     * @return Collection<int, CategorySpecification>
     */
    public function effectiveSpecificationAssignments(): Collection
    {
        $effective = collect();

        foreach ($this->lineage() as $category) {
            $assignments = $category
                ->availableSpecificationAssignments()
                ->with('specificationDefinition')
                ->get();

            foreach ($assignments as $assignment) {
                $definitionId = (int) (
                    $assignment
                        ->specification_definition_id
                );

                /*
                 * put() replaces an inherited parent assignment when the
                 * current child category assigns the same definition.
                 */
                $effective->put(
                    $definitionId,
                    $assignment
                );
            }
        }

        return $effective
            ->sortBy([
                ['sort_order', 'asc'],
                ['id', 'asc'],
            ])
            ->values();
    }

    /**
     * Return effective required specifications including inheritance.
     *
     * @return Collection<int, CategorySpecification>
     */
    public function effectiveRequiredSpecifications(): Collection
    {
        return $this
            ->effectiveSpecificationAssignments()
            ->filter(
                static fn (
                    CategorySpecification $assignment
                ): bool =>
                    $assignment->isRequired()
            )
            ->values();
    }

    /**
     * Return effective filterable specifications including inheritance.
     *
     * @return Collection<int, CategorySpecification>
     */
    public function effectiveFilterableSpecifications(): Collection
    {
        return $this
            ->effectiveSpecificationAssignments()
            ->filter(
                static fn (
                    CategorySpecification $assignment
                ): bool =>
                    $assignment->isFilterable()
            )
            ->values();
    }

    /**
     * Return effective variant specifications including inheritance.
     *
     * @return Collection<int, CategorySpecification>
     */
    public function effectiveVariantSpecifications(): Collection
    {
        return $this
            ->effectiveSpecificationAssignments()
            ->filter(
                static fn (
                    CategorySpecification $assignment
                ): bool =>
                    $assignment->isVariantAttribute()
            )
            ->values();
    }

    /**
     * Return effective specification codes.
     *
     * @return array<int, string>
     */
    public function effectiveSpecificationCodes(): array
    {
        return $this
            ->effectiveSpecificationAssignments()
            ->map(
                static fn (
                    CategorySpecification $assignment
                ): string =>
                    $assignment->code()
            )
            ->values()
            ->all();
    }

    /**
     * Return effective required specification codes.
     *
     * @return array<int, string>
     */
    public function requiredSpecificationCodes(): array
    {
        return $this
            ->effectiveRequiredSpecifications()
            ->map(
                static fn (
                    CategorySpecification $assignment
                ): string =>
                    $assignment->code()
            )
            ->values()
            ->all();
    }

    /**
     * Return specification definitions for building a product form.
     *
     * @return array<int, array<string, mixed>>
     */
    public function specificationFormDefinitions(): array
    {
        return $this
            ->effectiveSpecificationAssignments()
            ->map(
                static fn (
                    CategorySpecification $assignment
                ): array =>
                    $assignment->toFormDefinition()
            )
            ->values()
            ->all();
    }

    /*
    |--------------------------------------------------------------------------
    | Deletion helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether this category can be safely deleted.
     */
    public function canBeDeleted(): bool
    {
        return !$this->hasChildren()
            && !$this->hasProducts();
    }
}