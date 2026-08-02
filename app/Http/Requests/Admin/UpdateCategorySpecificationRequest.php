<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\SpecificationDataType;
use App\Models\CategorySpecification;
use App\Models\SpecificationDefinition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Validation\Validator;

final class UpdateCategorySpecificationRequest extends FormRequest
{
    /**
     * Cached category specification resolved from the route.
     */
    private ?CategorySpecification $resolvedAssignment = null;

    /**
     * Authorization is handled by administrator route middleware.
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

            $normalized['help_text'] = $helpText !== ''
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
     * The specification definition cannot be changed through this request.
     * A new category assignment must be created when another definition is
     * required.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'label' => [
                'sometimes',
                'nullable',
                'string',
                'max:150',
            ],

            'help_text' => [
                'sometimes',
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
                'sometimes',
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
            |
            | Sending null or an empty array clears the category override.
            | The reusable definition options will then be used.
            |
            */

            'options' => [
                'sometimes',
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
            | Category-specific default value
            |--------------------------------------------------------------------------
            */

            'default_value' => [
                'sometimes',
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
     * Perform validation using the complete effective assignment state.
     */
    public function withValidator(
        Validator $validator
    ): void {
        $validator->after(
            function (Validator $validator): void {
                $assignment = $this
                    ->categorySpecification();

                if ($assignment === null) {
                    $validator->errors()->add(
                        'category_specification',
                        'The category specification assignment could not be resolved.'
                    );

                    return;
                }

                $definition = $this
                    ->specificationDefinition(
                        $assignment
                    );

                if ($definition === null) {
                    $validator->errors()->add(
                        'category_specification',
                        'The reusable specification definition could not be resolved.'
                    );

                    return;
                }

                $submittedOptions = $this
                    ->submittedOptions();

                if ($this->exists('options')) {
                    $this->validateOptionsUsage(
                        $validator,
                        $definition,
                        $submittedOptions
                    );

                    $this->validateOptionValues(
                        $validator,
                        $submittedOptions
                    );

                    $this
                        ->validateCategoryOptionsAreAllowed(
                            $validator,
                            $definition,
                            $submittedOptions
                        );
                }

                $validationRules = $this
                    ->effectiveValidationRules(
                        $assignment
                    );

                $this->validateRuleRanges(
                    $validator,
                    $validationRules
                );

                $this->validateRulesMatchDataType(
                    $validator,
                    $definition->data_type,
                    $validationRules
                );

                $defaultValue = $this
                    ->effectiveDefaultValue(
                        $assignment,
                        $definition
                    );

                if ($defaultValue !== null) {
                    $this->validateDefaultValue(
                        $validator,
                        $definition->data_type,
                        $this->effectiveOptionValues(
                            $assignment,
                            $definition
                        ),
                        $defaultValue
                    );
                }
            }
        );
    }

    /**
     * Return the category specification assignment resolved from the route.
     */
    public function categorySpecification(): ?CategorySpecification
    {
        if (
            $this->resolvedAssignment
            instanceof CategorySpecification
        ) {
            return $this->resolvedAssignment;
        }

        $routeValue = $this->route(
            'categorySpecification'
        );

        if ($routeValue === null) {
            $routeValue = $this->route(
                'category_specification'
            );
        }

        if ($routeValue === null) {
            $routeValue = $this->route(
                'specification'
            );
        }

        if (
            $routeValue
            instanceof CategorySpecification
        ) {
            $this->resolvedAssignment = $routeValue;

            return $this->resolvedAssignment;
        }

        if (
            is_string($routeValue)
            && trim($routeValue) !== ''
        ) {
            $this->resolvedAssignment =
                CategorySpecification::query()
                    ->where(
                        'public_id',
                        trim($routeValue)
                    )
                    ->first();

            return $this->resolvedAssignment;
        }

        if (
            is_int($routeValue)
            || (
                is_string($routeValue)
                && ctype_digit($routeValue)
            )
        ) {
            $this->resolvedAssignment =
                CategorySpecification::query()
                    ->find((int) $routeValue);

            return $this->resolvedAssignment;
        }

        return null;
    }

    /**
     * Return the reusable definition belonging to the assignment.
     */
    private function specificationDefinition(
        CategorySpecification $assignment
    ): ?SpecificationDefinition {
        if (
            $assignment->relationLoaded(
                'specificationDefinition'
            )
        ) {
            return $assignment
                ->specificationDefinition;
        }

        return $assignment
            ->specificationDefinition()
            ->first();
    }

    /**
     * Return explicitly submitted category options.
     *
     * @return array<int, mixed>
     */
    private function submittedOptions(): array
    {
        $options = $this->input(
            'options',
            []
        );

        return is_array($options)
            ? $options
            : [];
    }

    /**
     * Ensure options are only used for select-based definitions.
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
     * Ensure option values are scalar, non-empty and unique.
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
                || !array_key_exists(
                    'value',
                    $option
                )
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

            $comparisonKey = $this
                ->comparisonKey($value);

            if (
                array_key_exists(
                    $comparisonKey,
                    $seenValues
                )
            ) {
                $validator->errors()->add(
                    "options.{$index}.value",
                    'Each option value must be unique.'
                );

                continue;
            }

            $seenValues[$comparisonKey] = true;
        }
    }

    /**
     * Category options may restrict global options, but cannot add values
     * that are not available in the reusable definition.
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

        $allowedValues = collect(
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
                || !array_key_exists(
                    'value',
                    $option
                )
            ) {
                continue;
            }

            $comparisonKey = $this
                ->comparisonKey(
                    $option['value']
                );

            if (
                array_key_exists(
                    $comparisonKey,
                    $allowedValues
                )
            ) {
                continue;
            }

            $validator->errors()->add(
                "options.{$index}.value",
                'The category option value must exist in the reusable specification definition.'
            );
        }
    }

    /**
     * Return submitted validation rules or the stored assignment rules.
     *
     * @return array<string, mixed>
     */
    private function effectiveValidationRules(
        CategorySpecification $assignment
    ): array {
        if ($this->exists('validation_rules')) {
            $rules = $this->input(
                'validation_rules'
            );

            return is_array($rules)
                ? $rules
                : [];
        }

        return is_array(
            $assignment->validation_rules
        )
            ? $assignment->validation_rules
            : [];
    }

    /**
     * Validate related minimum and maximum values.
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
     * Validate one minimum and maximum configuration pair.
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
                str_replace(
                    '_',
                    ' ',
                    $maximumKey
                ),
                str_replace(
                    '_',
                    ' ',
                    $minimumKey
                )
            )
        );
    }

    /**
     * Ensure validation configuration matches the definition data type.
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
                && $dataType
                    !== SpecificationDataType::TEXT
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
                && $dataType
                    !== SpecificationDataType::MULTISELECT
            ) {
                $validator->errors()->add(
                    "validation_rules.{$key}",
                    'Item-count validation rules are only allowed for multiselect specifications.'
                );
            }
        }
    }

    /**
     * Return the effective category option values.
     *
     * Priority:
     *
     * 1. Submitted non-empty category options
     * 2. Existing non-empty category options
     * 3. Reusable definition options
     *
     * Sending null or an empty array clears the existing override and falls
     * back to the reusable definition options.
     *
     * @return array<int, mixed>
     */
    private function effectiveOptionValues(
        CategorySpecification $assignment,
        SpecificationDefinition $definition
    ): array {
        if ($this->exists('options')) {
            $submittedOptions = $this
                ->submittedOptions();

            if ($submittedOptions !== []) {
                return $this->optionValuesFromItems(
                    $submittedOptions
                );
            }

            return $definition->optionValues();
        }

        $existingOptions = is_array(
            $assignment->options
        )
            ? $assignment->options
            : [];

        if ($existingOptions !== []) {
            return $this->optionValuesFromItems(
                $existingOptions
            );
        }

        return $definition->optionValues();
    }

    /**
     * Extract values from option items.
     *
     * @param array<int, mixed> $options
     *
     * @return array<int, mixed>
     */
    private function optionValuesFromItems(
        array $options
    ): array {
        return collect($options)
            ->filter(
                static fn (mixed $option): bool =>
                    is_array($option)
                    && array_key_exists(
                        'value',
                        $option
                    )
            )
            ->pluck('value')
            ->values()
            ->all();
    }

    /**
     * Return the submitted, assigned or reusable default value.
     */
    private function effectiveDefaultValue(
        CategorySpecification $assignment,
        SpecificationDefinition $definition
    ): mixed {
        if ($this->exists('default_value')) {
            return $this->input(
                'default_value'
            );
        }

        if ($assignment->default_value !== null) {
            return $assignment->default_value;
        }

        return $definition->default_value;
    }

    /**
     * Validate the effective default value.
     *
     * @param array<int, mixed> $optionValues
     */
    private function validateDefaultValue(
        Validator $validator,
        SpecificationDataType $dataType,
        array $optionValues,
        mixed $defaultValue
    ): void {
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

        if (
            $dataType
            === SpecificationDataType::SELECT
        ) {
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
            $dataType
            === SpecificationDataType::MULTISELECT
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

                $comparisonKey = $this
                    ->comparisonKey($value);

                if (
                    array_key_exists(
                        $comparisonKey,
                        $seenDefaults
                    )
                ) {
                    $validator->errors()->add(
                        "default_value.{$index}",
                        'Each default value must be unique.'
                    );
                }

                $seenDefaults[$comparisonKey] = true;
            }
        }
    }

    /**
     * Determine whether a value matches its specification data type.
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
                    [
                        'value' => $value,
                    ],
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
        $comparisonKey = $this
            ->comparisonKey($value);

        foreach ($optionValues as $optionValue) {
            if (
                $comparisonKey
                === $this->comparisonKey(
                    $optionValue
                )
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create a stable, type-sensitive comparison key.
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
