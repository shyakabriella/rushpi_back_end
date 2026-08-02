<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\SpecificationDataType;
use App\Models\Category;
use App\Models\CategorySpecification;
use App\Models\SpecificationDefinition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StoreCategorySpecificationRequest extends FormRequest
{
    /**
     * Cached route category.
     */
    private ?Category $resolvedCategory = null;

    /**
     * Cached specification definition.
     */
    private ?SpecificationDefinition $resolvedDefinition = null;

    /**
     * Authorization is handled by the administrator route middleware.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize submitted values before validation.
     */
    protected function prepareForValidation(): void
    {
        $normalized = [];

        if ($this->exists('specification_definition_public_id')) {
            $normalized[
                'specification_definition_public_id'
            ] = trim(
                (string) $this->input(
                    'specification_definition_public_id',
                    ''
                )
            );
        }

        if ($this->exists('label')) {
            $label = trim(
                (string) $this->input('label', '')
            );

            $normalized['label'] = $label !== ''
                ? $label
                : null;
        }

        if ($this->exists('help_text')) {
            $helpText = trim(
                (string) $this->input(
                    'help_text',
                    ''
                )
            );

            $normalized['help_text'] =
                $helpText !== ''
                    ? $helpText
                    : null;
        }

        if (
            $this->exists('options')
            && is_array($this->input('options'))
        ) {
            $normalized['options'] = collect(
                $this->input('options')
            )
                ->map(
                    static function (
                        mixed $option
                    ): mixed {
                        if (!is_array($option)) {
                            return $option;
                        }

                        if (
                            array_key_exists('label', $option)
                            && is_string($option['label'])
                        ) {
                            $option['label'] = trim(
                                $option['label']
                            );
                        }

                        if (
                            array_key_exists('value', $option)
                            && is_string($option['value'])
                        ) {
                            $option['value'] = trim(
                                $option['value']
                            );
                        }

                        return $option;
                    }
                )
                ->values()
                ->all();
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    /**
     * Validation rules.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'specification_definition_public_id' => [
                'required',
                'string',
                'size:26',
                Rule::exists(
                    'specification_definitions',
                    'public_id'
                )->whereNull('deleted_at'),
            ],

            'label' => [
                'nullable',
                'string',
                'max:150',
            ],

            'help_text' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'is_required' => [
                'sometimes',
                'boolean',
            ],

            'is_filterable' => [
                'sometimes',
                'boolean',
            ],

            'is_variant_attribute' => [
                'sometimes',
                'boolean',
            ],

            'is_active' => [
                'sometimes',
                'boolean',
            ],

            /*
            |--------------------------------------------------------------------------
            | Category-specific validation overrides
            |--------------------------------------------------------------------------
            */

            'validation_rules' => [
                'nullable',
                'array',
            ],

            'validation_rules.min' => [
                'nullable',
                'numeric',
            ],

            'validation_rules.max' => [
                'nullable',
                'numeric',
            ],

            'validation_rules.step' => [
                'nullable',
                'numeric',
                'gt:0',
            ],

            'validation_rules.min_length' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'validation_rules.max_length' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'validation_rules.min_items' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'validation_rules.max_items' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'validation_rules.pattern' => [
                'nullable',
                'string',
                'max:500',
            ],

            /*
            |--------------------------------------------------------------------------
            | Category-specific option overrides
            |--------------------------------------------------------------------------
            */

            'options' => [
                'nullable',
                'array',
                'max:100',
            ],

            'options.*' => [
                'required',
                'array',
            ],

            'options.*.value' => [
                'required',
            ],

            'options.*.label' => [
                'required',
                'string',
                'max:150',
            ],

            /*
            |--------------------------------------------------------------------------
            | Category-specific default
            |--------------------------------------------------------------------------
            */

            'default_value' => [
                'nullable',
            ],

            'sort_order' => [
                'sometimes',
                'integer',
                'min:0',
                'max:4294967295',
            ],
        ];
    }

    /**
     * Perform validation that requires the category and definition.
     */
    public function withValidator(
        Validator $validator
    ): void {
        $validator->after(
            function (Validator $validator): void {
                $category = $this->category();
                $definition = $this->specificationDefinition();

                if (
                    $category === null
                    || $definition === null
                ) {
                    return;
                }

                $this->validateDefinitionStatus(
                    $validator,
                    $definition
                );

                $this->validateUniqueAssignment(
                    $validator,
                    $category,
                    $definition
                );

                $submittedOptions = $this->submittedOptions();

                $this->validateOptionsUsage(
                    $validator,
                    $definition,
                    $submittedOptions
                );

                $this->validateOptionValues(
                    $validator,
                    $submittedOptions
                );

                $this->validateCategoryOptionsAreAllowed(
                    $validator,
                    $definition,
                    $submittedOptions
                );

                $validationRules = $this->input(
                    'validation_rules',
                    []
                );

                if (!is_array($validationRules)) {
                    $validationRules = [];
                }

                $this->validateRuleRanges(
                    $validator,
                    $validationRules
                );

                $this->validateRulesMatchDataType(
                    $validator,
                    $definition->data_type,
                    $validationRules
                );

                if ($this->exists('default_value')) {
                    $this->validateDefaultValue(
                        $validator,
                        $definition->data_type,
                        $this->effectiveOptions(
                            $definition,
                            $submittedOptions
                        ),
                        $this->input('default_value')
                    );
                }
            }
        );
    }

    /**
     * Return the category resolved from the route.
     */
    public function category(): ?Category
    {
        if ($this->resolvedCategory instanceof Category) {
            return $this->resolvedCategory;
        }

        $routeValue = $this->route('category');

        if ($routeValue instanceof Category) {
            $this->resolvedCategory = $routeValue;

            return $this->resolvedCategory;
        }

        if (
            is_string($routeValue)
            && trim($routeValue) !== ''
        ) {
            $this->resolvedCategory = Category::query()
                ->where(
                    'public_id',
                    trim($routeValue)
                )
                ->first();

            return $this->resolvedCategory;
        }

        if (
            is_int($routeValue)
            || (
                is_string($routeValue)
                && ctype_digit($routeValue)
            )
        ) {
            $this->resolvedCategory = Category::query()
                ->find((int) $routeValue);

            return $this->resolvedCategory;
        }

        return null;
    }

    /**
     * Return the submitted specification definition.
     */
    public function specificationDefinition(): ?SpecificationDefinition
    {
        if (
            $this->resolvedDefinition
            instanceof SpecificationDefinition
        ) {
            return $this->resolvedDefinition;
        }

        $publicId = trim(
            (string) $this->input(
                'specification_definition_public_id',
                ''
            )
        );

        if ($publicId === '') {
            return null;
        }

        $this->resolvedDefinition =
            SpecificationDefinition::query()
                ->where('public_id', $publicId)
                ->first();

        return $this->resolvedDefinition;
    }

    /**
     * Ensure inactive definitions cannot receive new assignments.
     */
    private function validateDefinitionStatus(
        Validator $validator,
        SpecificationDefinition $definition
    ): void {
        if ($definition->is_active) {
            return;
        }

        $validator->errors()->add(
            'specification_definition_public_id',
            'The selected specification definition is inactive.'
        );
    }

    /**
     * Prevent assigning the same definition twice to one category.
     */
    private function validateUniqueAssignment(
        Validator $validator,
        Category $category,
        SpecificationDefinition $definition
    ): void {
        $exists = CategorySpecification::query()
            ->where(
                'category_id',
                $category->getKey()
            )
            ->where(
                'specification_definition_id',
                $definition->getKey()
            )
            ->exists();

        if (!$exists) {
            return;
        }

        $validator->errors()->add(
            'specification_definition_public_id',
            'This specification definition is already assigned to the selected category.'
        );
    }

    /**
     * Return explicitly submitted category options.
     *
     * @return array<int, mixed>
     */
    private function submittedOptions(): array
    {
        $options = $this->input('options', []);

        return is_array($options)
            ? $options
            : [];
    }

    /**
     * Ensure options are only used for select-based specifications.
     *
     * @param array<int, mixed> $options
     */
    private function validateOptionsUsage(
        Validator $validator,
        SpecificationDefinition $definition,
        array $options
    ): void {
        if ($options === []) {
            return;
        }

        if ($definition->data_type->usesOptions()) {
            return;
        }

        $validator->errors()->add(
            'options',
            sprintf(
                'Category-specific options are not allowed for the %s data type.',
                $definition->data_type->value
            )
        );
    }

    /**
     * Ensure category option values are valid and unique.
     *
     * @param array<int, mixed> $options
     */
    private function validateOptionValues(
        Validator $validator,
        array $options
    ): void {
        $seenValues = [];

        foreach ($options as $index => $option) {
            if (
                !is_array($option)
                || !array_key_exists('value', $option)
            ) {
                continue;
            }

            $value = $option['value'];

            if (
                !is_string($value)
                && !is_int($value)
                && !is_float($value)
                && !is_bool($value)
            ) {
                $validator->errors()->add(
                    "options.{$index}.value",
                    'Each option value must be a string, number or boolean.'
                );

                continue;
            }

            if (
                is_string($value)
                && trim($value) === ''
            ) {
                $validator->errors()->add(
                    "options.{$index}.value",
                    'Each option value must not be empty.'
                );

                continue;
            }

            $key = $this->comparisonKey($value);

            if (array_key_exists($key, $seenValues)) {
                $validator->errors()->add(
                    "options.{$index}.value",
                    'Each option value must be unique.'
                );

                continue;
            }

            $seenValues[$key] = true;
        }
    }

    /**
     * Category options may restrict global options, but cannot introduce
     * values that do not exist in the reusable definition.
     *
     * @param array<int, mixed> $options
     */
    private function validateCategoryOptionsAreAllowed(
        Validator $validator,
        SpecificationDefinition $definition,
        array $options
    ): void {
        if (
            $options === []
            || !$definition->data_type->usesOptions()
        ) {
            return;
        }

        $definitionValues = collect(
            $definition->optionValues()
        )
            ->mapWithKeys(
                fn (mixed $value): array => [
                    $this->comparisonKey($value) => true,
                ]
            )
            ->all();

        foreach ($options as $index => $option) {
            if (
                !is_array($option)
                || !array_key_exists('value', $option)
            ) {
                continue;
            }

            $key = $this->comparisonKey(
                $option['value']
            );

            if (array_key_exists($key, $definitionValues)) {
                continue;
            }

            $validator->errors()->add(
                "options.{$index}.value",
                'The category option value must exist in the reusable specification definition.'
            );
        }
    }

    /**
     * Validate minimum and maximum configuration pairs.
     *
     * @param array<string, mixed> $rules
     */
    private function validateRuleRanges(
        Validator $validator,
        array $rules
    ): void {
        $this->validateMinimumMaximumPair(
            $validator,
            $rules,
            'min',
            'max'
        );

        $this->validateMinimumMaximumPair(
            $validator,
            $rules,
            'min_length',
            'max_length'
        );

        $this->validateMinimumMaximumPair(
            $validator,
            $rules,
            'min_items',
            'max_items'
        );
    }

    /**
     * Validate one minimum and maximum pair.
     *
     * @param array<string, mixed> $rules
     */
    private function validateMinimumMaximumPair(
        Validator $validator,
        array $rules,
        string $minimumKey,
        string $maximumKey
    ): void {
        if (
            !array_key_exists($minimumKey, $rules)
            || !array_key_exists($maximumKey, $rules)
            || !is_numeric($rules[$minimumKey])
            || !is_numeric($rules[$maximumKey])
        ) {
            return;
        }

        if (
            (float) $rules[$minimumKey]
            <= (float) $rules[$maximumKey]
        ) {
            return;
        }

        $validator->errors()->add(
            "validation_rules.{$maximumKey}",
            sprintf(
                'The %s must be greater than or equal to the %s.',
                str_replace('_', ' ', $maximumKey),
                str_replace('_', ' ', $minimumKey)
            )
        );
    }

    /**
     * Ensure validation configuration is appropriate for the data type.
     *
     * @param array<string, mixed> $rules
     */
    private function validateRulesMatchDataType(
        Validator $validator,
        SpecificationDataType $dataType,
        array $rules
    ): void {
        $numericKeys = [
            'min',
            'max',
            'step',
        ];

        $textKeys = [
            'min_length',
            'max_length',
            'pattern',
        ];

        $collectionKeys = [
            'min_items',
            'max_items',
        ];

        foreach ($numericKeys as $key) {
            if (
                array_key_exists($key, $rules)
                && !$dataType->isNumeric()
            ) {
                $validator->errors()->add(
                    "validation_rules.{$key}",
                    'Numeric validation rules are only allowed for integer and decimal specifications.'
                );
            }
        }

        foreach ($textKeys as $key) {
            if (
                array_key_exists($key, $rules)
                && $dataType !== SpecificationDataType::TEXT
            ) {
                $validator->errors()->add(
                    "validation_rules.{$key}",
                    'Text validation rules are only allowed for text specifications.'
                );
            }
        }

        foreach ($collectionKeys as $key) {
            if (
                array_key_exists($key, $rules)
                && $dataType !== SpecificationDataType::MULTISELECT
            ) {
                $validator->errors()->add(
                    "validation_rules.{$key}",
                    'Item-count validation rules are only allowed for multiselect specifications.'
                );
            }
        }
    }

    /**
     * Return category options when supplied, otherwise global options.
     *
     * @param array<int, mixed> $submittedOptions
     *
     * @return array<int, mixed>
     */
    private function effectiveOptions(
        SpecificationDefinition $definition,
        array $submittedOptions
    ): array {
        if ($submittedOptions !== []) {
            return collect($submittedOptions)
                ->filter(
                    static fn (mixed $option): bool =>
                        is_array($option)
                        && array_key_exists('value', $option)
                )
                ->pluck('value')
                ->values()
                ->all();
        }

        return $definition->optionValues();
    }

    /**
     * Validate the category-specific default value.
     *
     * @param array<int, mixed> $optionValues
     */
    private function validateDefaultValue(
        Validator $validator,
        SpecificationDataType $dataType,
        array $optionValues,
        mixed $defaultValue
    ): void {
        if ($defaultValue === null) {
            return;
        }

        if (
            !$this->valueMatchesType(
                $dataType,
                $defaultValue
            )
        ) {
            $validator->errors()->add(
                'default_value',
                sprintf(
                    'The default value must match the %s data type.',
                    $dataType->value
                )
            );

            return;
        }

        if (!$dataType->usesOptions()) {
            return;
        }

        if ($dataType === SpecificationDataType::SELECT) {
            if (
                !$this->valueExistsInOptions(
                    $defaultValue,
                    $optionValues
                )
            ) {
                $validator->errors()->add(
                    'default_value',
                    'The default value must be one of the effective option values.'
                );
            }

            return;
        }

        if (
            $dataType === SpecificationDataType::MULTISELECT
            && is_array($defaultValue)
        ) {
            $seenDefaults = [];

            foreach (
                $defaultValue as $index => $value
            ) {
                if (
                    !$this->valueExistsInOptions(
                        $value,
                        $optionValues
                    )
                ) {
                    $validator->errors()->add(
                        "default_value.{$index}",
                        'Each default value must be one of the effective option values.'
                    );
                }

                $key = $this->comparisonKey($value);

                if (array_key_exists($key, $seenDefaults)) {
                    $validator->errors()->add(
                        "default_value.{$index}",
                        'Each default value must be unique.'
                    );
                }

                $seenDefaults[$key] = true;
            }
        }
    }

    /**
     * Determine whether a value matches a specification data type.
     */
    private function valueMatchesType(
        SpecificationDataType $dataType,
        mixed $value
    ): bool {
        return match ($dataType) {
            SpecificationDataType::TEXT,
            SpecificationDataType::SELECT =>
                is_string($value),

            SpecificationDataType::INTEGER =>
                filter_var(
                    $value,
                    FILTER_VALIDATE_INT
                ) !== false,

            SpecificationDataType::DECIMAL =>
                is_numeric($value),

            SpecificationDataType::BOOLEAN =>
                is_bool($value)
                || in_array(
                    $value,
                    [
                        0,
                        1,
                        '0',
                        '1',
                    ],
                    true
                ),

            SpecificationDataType::MULTISELECT =>
                is_array($value),

            SpecificationDataType::DATE =>
                is_string($value)
                && !ValidatorFacade::make(
                    ['value' => $value],
                    [
                        'value' => [
                            'required',
                            'date',
                        ],
                    ]
                )->fails(),
        };
    }

    /**
     * Determine whether a value exists among allowed options.
     *
     * @param array<int, mixed> $optionValues
     */
    private function valueExistsInOptions(
        mixed $value,
        array $optionValues
    ): bool {
        $key = $this->comparisonKey($value);

        foreach ($optionValues as $optionValue) {
            if (
                $key
                === $this->comparisonKey($optionValue)
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create a stable type-sensitive comparison key.
     */
    private function comparisonKey(
        mixed $value
    ): string {
        return get_debug_type($value)
            .':'
            .json_encode(
                $value,
                JSON_THROW_ON_ERROR
            );
    }

    /**
     * Custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'specification_definition_public_id.required' =>
                'A specification definition is required.',

            'specification_definition_public_id.exists' =>
                'The selected specification definition does not exist.',

            'specification_definition_public_id.size' =>
                'The specification definition identifier must be a valid ULID.',

            'options.max' =>
                'A category specification cannot contain more than 100 options.',

            'sort_order.max' =>
                'The sort order value is too large.',
        ];
    }

    /**
     * Human-readable attribute names.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'specification_definition_public_id' =>
                'specification definition',

            'is_required' =>
                'required status',

            'is_filterable' =>
                'filterable status',

            'is_variant_attribute' =>
                'variant attribute status',

            'validation_rules.min_length' =>
                'minimum length',

            'validation_rules.max_length' =>
                'maximum length',

            'validation_rules.min_items' =>
                'minimum items',

            'validation_rules.max_items' =>
                'maximum items',
        ];
    }
}
