<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\SpecificationDataType;
use App\Models\SpecificationDefinition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateSpecificationDefinitionRequest extends FormRequest
{
    /**
     * Cached specification definition resolved from the route.
     */
    private ?SpecificationDefinition $resolvedDefinition = null;

    /**
     * Authorization is handled by authenticated administrator routes.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize submitted values before validation.
     *
     * The specification code remains unchanged when only the name changes.
     * A new code is generated only when the code field is explicitly sent.
     */
    protected function prepareForValidation(): void
    {
        $normalized = [];

        if ($this->exists('name')) {
            $normalized['name'] = trim(
                (string) $this->input('name', '')
            );
        }

        if ($this->exists('code')) {
            $submittedCode = trim(
                (string) $this->input('code', '')
            );

            $normalized['code'] = $submittedCode !== ''
                ? Str::snake($submittedCode)
                : '';
        }

        if ($this->exists('description')) {
            $description = trim(
                (string) $this->input(
                    'description',
                    ''
                )
            );

            $normalized['description'] =
                $description !== ''
                    ? $description
                    : null;
        }

        if ($this->exists('unit')) {
            $unit = trim(
                (string) $this->input(
                    'unit',
                    ''
                )
            );

            $normalized['unit'] =
                $unit !== ''
                    ? $unit
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
                            array_key_exists(
                                'label',
                                $option
                            )
                            && is_string(
                                $option['label']
                            )
                        ) {
                            $option['label'] = trim(
                                $option['label']
                            );
                        }

                        if (
                            array_key_exists(
                                'value',
                                $option
                            )
                            && is_string(
                                $option['value']
                            )
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
        $uniqueCodeRule = Rule::unique(
            'specification_definitions',
            'code'
        );

        $definition = $this->currentDefinition();

        if ($definition !== null) {
            $uniqueCodeRule->ignore(
                $definition->getKey()
            );
        }

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:150',
            ],

            'code' => [
                'sometimes',
                'required',
                'string',
                'max:150',
                'regex:/^[a-z][a-z0-9_]*$/',
                $uniqueCodeRule,
            ],

            'description' => [
                'sometimes',
                'nullable',
                'string',
                'max:5000',
            ],

            'data_type' => [
                'sometimes',
                'required',
                Rule::enum(
                    SpecificationDataType::class
                ),
            ],

            'unit' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
            ],

            /*
            |--------------------------------------------------------------------------
            | Selectable options
            |--------------------------------------------------------------------------
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
            | Dynamic validation configuration
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
            | Optional default value
            |--------------------------------------------------------------------------
            */

            'default_value' => [
                'sometimes',
                'nullable',
            ],

            /*
            |--------------------------------------------------------------------------
            | Catalog behavior
            |--------------------------------------------------------------------------
            */

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

            'sort_order' => [
                'sometimes',
                'integer',
                'min:0',
                'max:4294967295',
            ],
        ];
    }

    /**
     * Perform validation that depends on the complete effective state.
     *
     * For fields omitted from a PATCH request, the currently stored values
     * are used during cross-field validation.
     */
    public function withValidator(
        Validator $validator
    ): void {
        $validator->after(
            function (
                Validator $validator
            ): void {
                $type = $this->effectiveDataType();

                if ($type === null) {
                    return;
                }

                $options = $this->effectiveOptions();

                $this->validateOptionsUsage(
                    $validator,
                    $type,
                    $options
                );

                $this->validateOptionValues(
                    $validator,
                    $options
                );

                $this->validateRuleRanges(
                    $validator,
                    $this->effectiveValidationRules()
                );

                $defaultValue = $this
                    ->effectiveDefaultValue();

                if ($defaultValue !== null) {
                    $this->validateDefaultValue(
                        $validator,
                        $type,
                        $options,
                        $defaultValue
                    );
                }
            }
        );
    }

    /**
     * Resolve the current specification definition from route binding.
     */
    private function currentDefinition(): ?SpecificationDefinition
    {
        if (
            $this->resolvedDefinition
            instanceof SpecificationDefinition
        ) {
            return $this->resolvedDefinition;
        }

        $routeValue = $this->route(
            'specificationDefinition'
        );

        if ($routeValue === null) {
            $routeValue = $this->route(
                'specification_definition'
            );
        }

        if ($routeValue === null) {
            $routeValue = $this->route(
                'definition'
            );
        }

        if ($routeValue === null) {
            $routeValue = $this->route(
                'specification'
            );
        }

        if (
            $routeValue
            instanceof SpecificationDefinition
        ) {
            $this->resolvedDefinition = $routeValue;

            return $this->resolvedDefinition;
        }

        if (
            is_string($routeValue)
            && trim($routeValue) !== ''
        ) {
            $this->resolvedDefinition =
                SpecificationDefinition::query()
                    ->where(
                        'public_id',
                        trim($routeValue)
                    )
                    ->first();

            return $this->resolvedDefinition;
        }

        if (
            is_int($routeValue)
            || (
                is_string($routeValue)
                && ctype_digit($routeValue)
            )
        ) {
            $this->resolvedDefinition =
                SpecificationDefinition::query()
                    ->find((int) $routeValue);

            return $this->resolvedDefinition;
        }

        return null;
    }

    /**
     * Return the submitted data type or the currently stored data type.
     */
    private function effectiveDataType(): ?SpecificationDataType
    {
        if ($this->exists('data_type')) {
            return SpecificationDataType::tryFrom(
                (string) $this->input(
                    'data_type'
                )
            );
        }

        $definition = $this->currentDefinition();

        if ($definition === null) {
            return null;
        }

        if (
            $definition->data_type
            instanceof SpecificationDataType
        ) {
            return $definition->data_type;
        }

        return SpecificationDataType::tryFrom(
            (string) $definition->data_type
        );
    }

    /**
     * Return submitted options or currently stored options.
     *
     * @return array<int, mixed>
     */
    private function effectiveOptions(): array
    {
        if ($this->exists('options')) {
            $options = $this->input('options');

            return is_array($options)
                ? $options
                : [];
        }

        $definition = $this->currentDefinition();

        if (
            $definition === null
            || !is_array($definition->options)
        ) {
            return [];
        }

        return $definition->options;
    }

    /**
     * Return submitted validation rules or currently stored rules.
     *
     * @return array<string, mixed>
     */
    private function effectiveValidationRules(): array
    {
        if ($this->exists('validation_rules')) {
            $rules = $this->input(
                'validation_rules'
            );

            return is_array($rules)
                ? $rules
                : [];
        }

        $definition = $this->currentDefinition();

        if (
            $definition === null
            || !is_array(
                $definition->validation_rules
            )
        ) {
            return [];
        }

        return $definition->validation_rules;
    }

    /**
     * Return submitted default value or the currently stored default.
     */
    private function effectiveDefaultValue(): mixed
    {
        if ($this->exists('default_value')) {
            return $this->input(
                'default_value'
            );
        }

        return $this
            ->currentDefinition()
            ?->default_value;
    }

    /**
     * Ensure selectable options are used by supported data types.
     *
     * @param array<int, mixed> $options
     */
    private function validateOptionsUsage(
        Validator $validator,
        SpecificationDataType $type,
        array $options
    ): void {
        if (
            $type->usesOptions()
            && $options === []
        ) {
            $validator
                ->errors()
                ->add(
                    'options',
                    sprintf(
                        'The options field is required when the data type is %s.',
                        $type->value
                    )
                );

            return;
        }

        if (
            !$type->usesOptions()
            && $options !== []
        ) {
            $validator
                ->errors()
                ->add(
                    'options',
                    sprintf(
                        'Options are only allowed for %s and %s specifications.',
                        SpecificationDataType::SELECT
                            ->value,
                        SpecificationDataType::MULTISELECT
                            ->value
                    )
                );
        }
    }

    /**
     * Ensure option values are scalar and unique.
     *
     * @param array<int, mixed> $options
     */
    private function validateOptionValues(
        Validator $validator,
        array $options
    ): void {
        $seenValues = [];

        foreach (
            $options as $index => $option
        ) {
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
                $validator
                    ->errors()
                    ->add(
                        "options.{$index}.value",
                        'Each option value must be a string, number or boolean.'
                    );

                continue;
            }

            if (
                is_string($value)
                && trim($value) === ''
            ) {
                $validator
                    ->errors()
                    ->add(
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
                $validator
                    ->errors()
                    ->add(
                        "options.{$index}.value",
                        'Each option value must be unique.'
                    );

                continue;
            }

            $seenValues[$comparisonKey] = true;
        }
    }

    /**
     * Validate related minimum and maximum rules.
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
            !array_key_exists(
                $minimumKey,
                $rules
            )
            || !array_key_exists(
                $maximumKey,
                $rules
            )
            || !is_numeric(
                $rules[$minimumKey]
            )
            || !is_numeric(
                $rules[$maximumKey]
            )
        ) {
            return;
        }

        if (
            (float) $rules[$minimumKey]
            <= (float) $rules[$maximumKey]
        ) {
            return;
        }

        $validator
            ->errors()
            ->add(
                "validation_rules.{$maximumKey}",
                sprintf(
                    'The %s value must be greater than or equal to %s.',
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
     * Validate the effective default value against the effective data type.
     *
     * @param array<int, mixed> $options
     */
    private function validateDefaultValue(
        Validator $validator,
        SpecificationDataType $type,
        array $options,
        mixed $defaultValue
    ): void {
        if (
            !$this->defaultValueMatchesType(
                $type,
                $defaultValue
            )
        ) {
            $validator
                ->errors()
                ->add(
                    'default_value',
                    sprintf(
                        'The default value must match the %s data type.',
                        $type->value
                    )
                );

            return;
        }

        if (!$type->usesOptions()) {
            return;
        }

        $optionValues = collect($options)
            ->filter(
                static fn (
                    mixed $option
                ): bool =>
                    is_array($option)
                    && array_key_exists(
                        'value',
                        $option
                    )
            )
            ->pluck('value')
            ->values()
            ->all();

        if (
            $type ===
            SpecificationDataType::SELECT
        ) {
            if (
                !$this->valueExistsInOptions(
                    $defaultValue,
                    $optionValues
                )
            ) {
                $validator
                    ->errors()
                    ->add(
                        'default_value',
                        'The default value must be one of the configured option values.'
                    );
            }

            return;
        }

        if (
            $type ===
            SpecificationDataType::MULTISELECT
            && is_array($defaultValue)
        ) {
            foreach (
                $defaultValue as $index => $value
            ) {
                if (
                    !$this->valueExistsInOptions(
                        $value,
                        $optionValues
                    )
                ) {
                    $validator
                        ->errors()
                        ->add(
                            "default_value.{$index}",
                            'Each default value must be one of the configured option values.'
                        );
                }
            }
        }
    }

    /**
     * Determine whether a default value matches a data type.
     */
    private function defaultValueMatchesType(
        SpecificationDataType $type,
        mixed $value
    ): bool {
        return match ($type) {
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
     * Determine whether a value exists in configured options.
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
            'code.regex' =>
                'The specification code must start with a lowercase letter and may only contain lowercase letters, numbers and underscores.',

            'code.unique' =>
                'Another specification definition already uses this code.',

            'options.max' =>
                'A specification cannot contain more than 100 selectable options.',

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
            'data_type' =>
                'data type',

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
