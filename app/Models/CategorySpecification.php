<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SpecificationDataType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategorySpecification extends Model
{
    use HasFactory;
    use HasUlids;

    /**
     * Attributes that may be mass assigned.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'category_id',
        'specification_definition_id',
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
    ];

    /**
     * Attribute casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'options' => 'array',

            'validation_rules' => 'array',

            'is_required' => 'boolean',

            'is_filterable' => 'boolean',

            'is_variant_attribute' => 'boolean',

            'is_active' => 'boolean',

            'sort_order' => 'integer',

            'created_at' => 'datetime',

            'updated_at' => 'datetime',
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
     * Safely cast the JSON default value.
     *
     * The default value may be:
     *
     * - string
     * - integer
     * - decimal
     * - boolean
     * - array
     * - null
     *
     * @return Attribute<mixed, mixed>
     */
    protected function defaultValue(): Attribute
    {
        return Attribute::make(
            get: static function (mixed $value): mixed {
                if ($value === null) {
                    return null;
                }

                if (!is_string($value)) {
                    return $value;
                }

                return json_decode(
                    $value,
                    true,
                    512,
                    JSON_THROW_ON_ERROR
                );
            },

            set: static function (mixed $value): ?string {
                if ($value === null) {
                    return null;
                }

                return json_encode(
                    $value,
                    JSON_THROW_ON_ERROR
                );
            }
        );
    }

    /**
     * Normalize text fields before persistence.
     */
    protected static function booted(): void
    {
        static::saving(
            static function (
                CategorySpecification $assignment
            ): void {
                if ($assignment->label !== null) {
                    $label = trim(
                        (string) $assignment->label
                    );

                    $assignment->label = $label !== ''
                        ? $label
                        : null;
                }

                if ($assignment->help_text !== null) {
                    $helpText = trim(
                        (string) $assignment->help_text
                    );

                    $assignment->help_text =
                        $helpText !== ''
                            ? $helpText
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
     * Category receiving this specification assignment.
     *
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(
            Category::class,
            'category_id'
        );
    }

    /**
     * Reusable specification definition.
     *
     * @return BelongsTo<SpecificationDefinition, $this>
     */
    public function specificationDefinition(): BelongsTo
    {
        return $this->belongsTo(
            SpecificationDefinition::class,
            'specification_definition_id'
        );
    }

    /**
     * User who created the assignment.
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
     * User who last updated the assignment.
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
     * Restrict results to active assignments.
     *
     * @param Builder<CategorySpecification> $query
     *
     * @return Builder<CategorySpecification>
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
     * Restrict results to required specifications.
     *
     * @param Builder<CategorySpecification> $query
     *
     * @return Builder<CategorySpecification>
     */
    public function scopeRequired(
        Builder $query
    ): Builder {
        return $query->where(
            'is_required',
            true
        );
    }

    /**
     * Restrict results to filterable specifications.
     *
     * @param Builder<CategorySpecification> $query
     *
     * @return Builder<CategorySpecification>
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
     * Restrict results to variant attributes.
     *
     * @param Builder<CategorySpecification> $query
     *
     * @return Builder<CategorySpecification>
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
     * Restrict assignments to a category.
     *
     * The category may be supplied using its internal ID.
     *
     * @param Builder<CategorySpecification> $query
     *
     * @return Builder<CategorySpecification>
     */
    public function scopeForCategory(
        Builder $query,
        int $categoryId
    ): Builder {
        return $query->where(
            'category_id',
            $categoryId
        );
    }

    /**
     * Restrict results to assignments whose reusable definition is active.
     *
     * @param Builder<CategorySpecification> $query
     *
     * @return Builder<CategorySpecification>
     */
    public function scopeWithActiveDefinition(
        Builder $query
    ): Builder {
        return $query->whereHas(
            'specificationDefinition',
            static function (
                Builder $definitionQuery
            ): void {
                $definitionQuery->where(
                    'is_active',
                    true
                );
            }
        );
    }

    /**
     * Restrict results to usable active assignments.
     *
     * @param Builder<CategorySpecification> $query
     *
     * @return Builder<CategorySpecification>
     */
    public function scopeAvailable(
        Builder $query
    ): Builder {
        return $query
            ->active()
            ->withActiveDefinition();
    }

    /**
     * Apply the standard form display order.
     *
     * @param Builder<CategorySpecification> $query
     *
     * @return Builder<CategorySpecification>
     */
    public function scopeOrdered(
        Builder $query
    ): Builder {
        return $query
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /*
    |--------------------------------------------------------------------------
    | Effective configuration
    |--------------------------------------------------------------------------
    */

    /**
     * Return the reusable specification definition.
     */
    private function definition(): SpecificationDefinition
    {
        if (
            $this->relationLoaded(
                'specificationDefinition'
            )
            && $this->specificationDefinition !== null
        ) {
            return $this->specificationDefinition;
        }

        return $this
            ->specificationDefinition()
            ->firstOrFail();
    }

    /**
     * Return the category-specific label or the definition name.
     */
    public function effectiveLabel(): string
    {
        if (
            $this->label !== null
            && trim((string) $this->label) !== ''
        ) {
            return (string) $this->label;
        }

        return (string) $this->definition()->name;
    }

    /**
     * Return category help text or the reusable description.
     */
    public function effectiveHelpText(): ?string
    {
        if (
            $this->help_text !== null
            && trim((string) $this->help_text) !== ''
        ) {
            return (string) $this->help_text;
        }

        return $this->definition()->description;
    }

    /**
     * Return the machine-readable specification code.
     */
    public function code(): string
    {
        return (string) $this->definition()->code;
    }

    /**
     * Return the effective specification data type.
     */
    public function dataType(): SpecificationDataType
    {
        return $this->definition()->data_type;
    }

    /**
     * Return the effective measurement unit.
     */
    public function unit(): ?string
    {
        return $this->definition()->unit;
    }

    /**
     * Return the API-facing value type.
     */
    public function apiType(): string
    {
        return $this
            ->dataType()
            ->apiType();
    }

    /**
     * Determine whether selectable options are used.
     */
    public function usesOptions(): bool
    {
        return $this
            ->dataType()
            ->usesOptions();
    }

    /**
     * Determine whether multiple values are accepted.
     */
    public function acceptsMultipleValues(): bool
    {
        return $this
            ->dataType()
            ->acceptsMultipleValues();
    }

    /**
     * Determine whether this is a numeric specification.
     */
    public function isNumeric(): bool
    {
        return $this
            ->dataType()
            ->isNumeric();
    }

    /*
    |--------------------------------------------------------------------------
    | Effective options
    |--------------------------------------------------------------------------
    */

    /**
     * Return category-specific options when provided.
     *
     * Otherwise, return the reusable definition options.
     *
     * @return array<int, array<string, mixed>>
     */
    public function effectiveOptions(): array
    {
        $assignmentOptions = $this->normalizeOptions(
            $this->options
        );

        if ($assignmentOptions !== []) {
            return $assignmentOptions;
        }

        return $this
            ->definition()
            ->optionItems();
    }

    /**
     * Return allowed option values.
     *
     * @return array<int, mixed>
     */
    public function effectiveOptionValues(): array
    {
        return collect(
            $this->effectiveOptions()
        )
            ->pluck('value')
            ->values()
            ->all();
    }

    /**
     * Normalize selectable options.
     *
     * @param array<int, mixed>|null $options
     *
     * @return array<int, array<string, mixed>>
     */
    private function normalizeOptions(
        ?array $options
    ): array {
        if ($options === null) {
            return [];
        }

        return collect($options)
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

    /*
    |--------------------------------------------------------------------------
    | Effective validation
    |--------------------------------------------------------------------------
    */

    /**
     * Return the merged validation configuration.
     *
     * Category-specific values replace reusable definition values.
     *
     * @return array<string, mixed>
     */
    public function effectiveValidationConfiguration(): array
    {
        $definitionRules = $this
            ->definition()
            ->validationConfiguration();

        $categoryRules = is_array(
            $this->validation_rules
        )
            ? $this->validation_rules
            : [];

        return array_replace_recursive(
            $definitionRules,
            $categoryRules
        );
    }

    /**
     * Return the base Laravel validation rules.
     *
     * Dynamic min, max, options and other configuration will be added by
     * the specification validation service.
     *
     * @return array<int, string>
     */
    public function baseValidationRules(): array
    {
        $rules = $this
            ->dataType()
            ->validationRules();

        array_unshift(
            $rules,
            $this->is_required
                ? 'required'
                : 'nullable'
        );

        return array_values(
            array_unique($rules)
        );
    }

    /**
     * Return the effective default value.
     */
    public function effectiveDefaultValue(): mixed
    {
        if ($this->default_value !== null) {
            return $this->default_value;
        }

        return $this
            ->definition()
            ->default_value;
    }

    /*
    |--------------------------------------------------------------------------
    | State helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Determine whether the assignment and definition are both active.
     */
    public function isAvailable(): bool
    {
        return $this->is_active
            && $this->definition()->is_active;
    }

    /**
     * Determine whether this specification must be supplied.
     */
    public function isRequired(): bool
    {
        return (bool) $this->is_required;
    }

    /**
     * Determine whether this field may be used for public filtering.
     */
    public function isFilterable(): bool
    {
        return (bool) $this->is_filterable;
    }

    /**
     * Determine whether this field may create product variants.
     */
    public function isVariantAttribute(): bool
    {
        return (bool) $this->is_variant_attribute;
    }

    /**
     * Return the complete effective field definition for API forms.
     *
     * @return array<string, mixed>
     */
    public function toFormDefinition(): array
    {
        return [
            'public_id' => (string) $this->public_id,

            'code' => $this->code(),

            'label' => $this->effectiveLabel(),

            'help_text' => $this->effectiveHelpText(),

            'data_type' => $this->dataType()->value,

            'data_type_label' => $this->dataType()->label(),

            'api_type' => $this->apiType(),

            'unit' => $this->unit(),

            'is_required' => $this->isRequired(),

            'is_filterable' => $this->isFilterable(),

            'is_variant_attribute' =>
                $this->isVariantAttribute(),

            'is_active' => (bool) $this->is_active,

            'options' => $this->effectiveOptions(),

            'validation_rules' =>
                $this->effectiveValidationConfiguration(),

            'default_value' =>
                $this->effectiveDefaultValue(),

            'sort_order' => (int) $this->sort_order,
        ];
    }
}
