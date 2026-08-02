<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SpecificationDataType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Support\Str;

class SpecificationDefinition extends Model
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
        'name',
        'code',
        'description',
        'data_type',
        'unit',
        'options',
        'validation_rules',
        'default_value',
        'is_filterable',
        'is_variant_attribute',
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
            'data_type' => SpecificationDataType::class,

            'options' => 'array',

            'validation_rules' => 'array',

            /*
             * The JSON cast supports scalar values, arrays and booleans.
             */
            'default_value' => 'json',

            'is_filterable' => 'boolean',

            'is_variant_attribute' => 'boolean',

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
     * Prepare model values before persistence.
     */
    protected static function booted(): void
    {
        static::saving(
            static function (
                SpecificationDefinition $definition
            ): void {
                $definition->name = trim(
                    (string) $definition->name
                );

                if (
                    $definition->code === null
                    || trim((string) $definition->code) === ''
                ) {
                    $definition->code = Str::snake(
                        $definition->name
                    );
                } else {
                    $definition->code = Str::snake(
                        trim((string) $definition->code)
                    );
                }

                if ($definition->unit !== null) {
                    $unit = trim(
                        (string) $definition->unit
                    );

                    $definition->unit = $unit !== ''
                        ? $unit
                        : null;
                }

                if ($definition->description !== null) {
                    $description = trim(
                        (string) $definition->description
                    );

                    $definition->description =
                        $description !== ''
                            ? $description
                            : null;
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
     * Category assignments using this specification definition.
     *
     * @return HasMany<CategorySpecification, $this>
     */
    public function categorySpecifications(): HasMany
    {
        return $this->hasMany(
            CategorySpecification::class,
            'specification_definition_id'
        );
    }

    /**
     * Categories that use this specification definition.
     *
     * @return BelongsToMany<Category, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this
            ->belongsToMany(
                Category::class,
                'category_specifications',
                'specification_definition_id',
                'category_id'
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

    /**
     * User who created the specification definition.
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
     * User who last updated the specification definition.
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
     * Restrict results to active definitions.
     *
     * @param Builder<SpecificationDefinition> $query
     *
     * @return Builder<SpecificationDefinition>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where(
            'is_active',
            true
        );
    }

    /**
     * Restrict results to filterable definitions.
     *
     * @param Builder<SpecificationDefinition> $query
     *
     * @return Builder<SpecificationDefinition>
     */
    public function scopeFilterable(
        Builder $query
    ): Builder {
        return $query->where(
            'is_filterable',
            true
        );
    }

    /**
     * Restrict results to variant attribute definitions.
     *
     * @param Builder<SpecificationDefinition> $query
     *
     * @return Builder<SpecificationDefinition>
     */
    public function scopeVariantAttributes(
        Builder $query
    ): Builder {
        return $query->where(
            'is_variant_attribute',
            true
        );
    }

    /**
     * Apply the standard display ordering.
     *
     * @param Builder<SpecificationDefinition> $query
     *
     * @return Builder<SpecificationDefinition>
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
     * Search definitions by name, code, description or unit.
     *
     * @param Builder<SpecificationDefinition> $query
     *
     * @return Builder<SpecificationDefinition>
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
                    ->orWhere('code', 'like', $like)
                    ->orWhere(
                        'description',
                        'like',
                        $like
                    )
                    ->orWhere('unit', 'like', $like);
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Type helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether selectable options are required.
     */
    public function usesOptions(): bool
    {
        return $this->data_type
            ->usesOptions();
    }

    /**
     * Determine whether the specification accepts multiple values.
     */
    public function acceptsMultipleValues(): bool
    {
        return $this->data_type
            ->acceptsMultipleValues();
    }

    /**
     * Determine whether the specification stores numeric values.
     */
    public function isNumeric(): bool
    {
        return $this->data_type
            ->isNumeric();
    }

    /**
     * Return the API-facing value type.
     */
    public function apiType(): string
    {
        return $this->data_type
            ->apiType();
    }

    /**
     * Return basic validation rules from the specification type.
     *
     * @return array<int, string>
     */
    public function baseValidationRules(): array
    {
        return $this->data_type
            ->validationRules();
    }

    /*
    |--------------------------------------------------------------------------
    | Options and validation helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Return configured selectable options.
     *
     * Each option should normally have:
     *
     * [
     *     'value' => '16',
     *     'label' => '16 GB',
     * ]
     *
     * @return array<int, array<string, mixed>>
     */
    public function optionItems(): array
    {
        if (!is_array($this->options)) {
            return [];
        }

        return collect($this->options)
            ->filter(
                static fn (mixed $option): bool =>
                    is_array($option)
                    && array_key_exists(
                        'value',
                        $option
                    )
            )
            ->map(
                static function (
                    array $option
                ): array {
                    $value = $option['value'];

                    return [
                        'value' => $value,

                        'label' => isset(
                            $option['label']
                        )
                            ? (string) $option['label']
                            : (string) $value,
                    ];
                }
            )
            ->values()
            ->all();
    }

    /**
     * Return allowed option values.
     *
     * @return array<int, mixed>
     */
    public function optionValues(): array
    {
        return collect(
            $this->optionItems()
        )
            ->pluck('value')
            ->values()
            ->all();
    }

    /**
     * Return definition-level validation configuration.
     *
     * @return array<string, mixed>
     */
    public function validationConfiguration(): array
    {
        return is_array(
            $this->validation_rules
        )
            ? $this->validation_rules
            : [];
    }

    /*
    |--------------------------------------------------------------------------
    | Assignment and deletion helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether this definition is assigned to any category.
     */
    public function isAssignedToCategories(): bool
    {
        if (
            $this->relationLoaded(
                'categorySpecifications'
            )
        ) {
            return $this
                ->categorySpecifications
                ->isNotEmpty();
        }

        return $this
            ->categorySpecifications()
            ->exists();
    }

    /**
     * Determine whether the definition may be safely deleted.
     *
     * Assigned definitions should normally be deactivated instead.
     */
    public function canBeDeleted(): bool
    {
        return !$this->isAssignedToCategories();
    }

    /**
     * Return active category assignments.
     *
     * @return Collection<int, CategorySpecification>
     */
    public function activeCategoryAssignments(): Collection
    {
        if (
            $this->relationLoaded(
                'categorySpecifications'
            )
        ) {
            return $this
                ->categorySpecifications
                ->filter(
                    static fn (
                        CategorySpecification $assignment
                    ): bool =>
                        $assignment->is_active
                )
                ->sortBy([
                    ['sort_order', 'asc'],
                    ['id', 'asc'],
                ])
                ->values();
        }

        return $this
            ->categorySpecifications()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }
}
