<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\SpecificationDataType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class StoreSpecificationDefinitionRequest extends FormRequest
{
    /**
     * Authorization is handled by the authenticated admin routes.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize incoming values before validation.
     */
    protected function prepareForValidation(): void
    {
        $name = trim(
            (string) $this->input('name', '')
        );

        $submittedCode = $this->input('code');

        $code = $submittedCode === null
            || trim((string) $submittedCode) === ''
                ? Str::snake($name)
                : Str::snake(
                    trim((string) $submittedCode)
                );

        $normalized = [
            'name' => $name,
            'code' => $code,
        ];

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
                            isset($option['label'])
                            && is_string(
                                $option['label']
                            )
                        ) {
                            $option['label'] = trim(
                                $option['label']
                            );
                        }

                        return $option;
                    }
                )
                ->values()
                ->all();
        }

        $this->merge($normalized);
    }

    /**
     * Validation rules.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'code' => [
                'required',
                'string',
                'max:150',
                'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique(
                    'specification_definitions',
                    'code'
                ),
            ],

            'description' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'data_type' => [
                'required',
                Rule::enum(
                    SpecificationDataType::class
                ),
            ],

            'unit' => [
                'nullable',
                'string',
                'max:50',
            ],

            /*
            |--------------------------------------------------------------------------
            | Selectable options
            |--------------------------------------------------------------------------
            |
            | Example:
            |
            | [
            |     {
            |         "value": "8",
            |         "label": "8 GB"
            |     },
            |     {
            |         "value": "16",
            |         "label": "16 GB"
            |     }
            | ]
            |
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
            | Dynamic validation configuration
            |--------------------------------------------------------------------------
            |
            | Numeric example:
            |
            | {
            |     "min": 1,
            |     "max": 128,
            |     "step": 1
            | }
            |
            | Text example:
            |
            | {
            |     "min_length": 2,
            |     "max_length": 100,
            |     "pattern": "^[A-Za-z0-9 ]+$"
            | }
            |
            | Multiselect example:
            |
            | {
            |     "min_items": 1,
            |     "max_items": 5
            | }
            |
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
            | Optional default value
            |--------------------------------------------------------------------------
            */

            'default_value' => [
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
     * Perform validation that depends on multiple fields.
     */
    public function withValidator(
        Validator $validator
    ): void {
        $validator->after(
            function (
                Validator $validator
            ): void {
                $type = SpecificationDataType::tryFrom(
                    (string) $this->input(
                        'data_type'
                    )
                );

                if ($type === null) {
                    return;
                }

                $options = $this->input(
                    'options',
                    []
                );

                if (!is_array($options)) {
                    $options = [];
                }

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
                    $validator
                );

                if ($this->exists('default_value')) {
                    $this->validateDefaultValue(
                        $validator,
                        $type,
                        $options
                    );
                }
            }
        );
    }

    /**
     * Ensure selectable options are used only by supported types.
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
     * Validate related minimum and maximum values.
     */
    private function validateRuleRanges(
        Validator $validator
    ): void {
        $rules = $this->input(
            'validation_rules',
            []
        );

        if (!is_array($rules)) {
            return;
        }

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
     * Validate one related minimum and maximum pair.
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
     * Validate the default value against the specification type.
     *
     * @param array<int, mixed> $options
     */
    private function validateDefaultValue(
        Validator $validator,
        SpecificationDataType $type,
        array $options
    ): void {
        $defaultValue = $this->input(
            'default_value'
        );

        if ($defaultValue === null) {
            return;
        }

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
     * Determine whether a default value matches its configured type.
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
     * Determine whether a value exists in the configured options.
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
            'code.regex' =>
                'The specification code must start with a lowercase letter and may only contain lowercase letters, numbers and underscores.',

            'code.unique' =>
                'A specification definition with this code already exists.',

            'data_type.required' =>
                'The specification data type is required.',

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
