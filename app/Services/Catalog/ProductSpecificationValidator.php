<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Enums\SpecificationDataType;
use App\Models\Category;
use App\Models\CategorySpecification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ProductSpecificationValidator
{
    /**
     * Validate specifications while saving a draft product.
     *
     * Required category specifications are not enforced at draft stage,
     * but every submitted value must still match its configured type,
     * options and validation rules.
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validateDraft(
        Category $category,
        mixed $specifications,
        string $attribute = 'specifications'
    ): array {
        return $this->validate(
            category: $category,
            specifications: $specifications,
            requireRequired: false,
            applyDefaults: true,
            attribute: $attribute
        );
    }

    /**
     * Validate specifications before submitting or publishing a product.
     *
     * All active required category specifications are enforced.
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validateForPublication(
        Category $category,
        mixed $specifications,
        string $attribute = 'specifications'
    ): array {
        return $this->validate(
            category: $category,
            specifications: $specifications,
            requireRequired: true,
            applyDefaults: true,
            attribute: $attribute
        );
    }

    /**
     * Validate and normalize category-controlled specification values.
     *
     * Product specification values are stored by specification code:
     *
     * [
     *     'processor' => 'Intel Core i7',
     *     'ram' => 16,
     *     'storage_capacity' => 512,
     *     'screen_size' => 15.6,
     *     'touchscreen' => true,
     * ]
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    public function validate(
        Category $category,
        mixed $specifications,
        bool $requireRequired = false,
        bool $applyDefaults = true,
        string $attribute = 'specifications'
    ): array {
        $attribute = trim($attribute);

        if ($attribute === '') {
            $attribute = 'specifications';
        }

        $errors = [];

        if ($specifications === null) {
            $specifications = [];
        }

        if (!is_array($specifications)) {
            throw ValidationException::withMessages([
                $attribute => [
                    'The specifications field must be an object.',
                ],
            ]);
        }

        $submittedValues = $this->normalizeInputKeys(
            specifications: $specifications,
            errors: $errors,
            attribute: $attribute
        );

        $assignments = $category
            ->effectiveSpecificationAssignments()
            ->keyBy(
                static fn (
                    CategorySpecification $assignment
                ): string => $assignment->code()
            );

        /*
        |--------------------------------------------------------------------------
        | Reject unknown specification codes
        |--------------------------------------------------------------------------
        */

        foreach (
            array_keys($submittedValues) as $submittedCode
        ) {
            if ($assignments->has($submittedCode)) {
                continue;
            }

            $errors["{$attribute}.{$submittedCode}"][] =
                sprintf(
                    'The %s specification is not configured for the %s category.',
                    str_replace('_', ' ', $submittedCode),
                    $category->name
                );
        }

        $normalizedValues = [];

        /*
        |--------------------------------------------------------------------------
        | Validate every effective category specification
        |--------------------------------------------------------------------------
        */

        foreach ($assignments as $code => $assignment) {
            $field = "{$attribute}.{$code}";

            $hasSubmittedValue = array_key_exists(
                $code,
                $submittedValues
            );

            $value = $hasSubmittedValue
                ? $submittedValues[$code]
                : null;

            /*
             * Apply the category-specific or reusable default value when
             * no explicit value was submitted.
             */

            if (
                !$hasSubmittedValue
                && $applyDefaults
            ) {
                $defaultValue = $assignment
                    ->effectiveDefaultValue();

                if (
                    $this->valueIsPresent(
                        $defaultValue
                    )
                ) {
                    $value = $defaultValue;
                    $hasSubmittedValue = true;
                }
            }

            /*
             * Empty optional values are omitted from stored JSON.
             */

            if (
                !$hasSubmittedValue
                || !$this->valueIsPresent($value)
            ) {
                if (
                    $requireRequired
                    && $assignment->isRequired()
                ) {
                    $errors[$field][] = sprintf(
                        'The %s specification is required.',
                        $assignment->effectiveLabel()
                    );
                }

                continue;
            }

            $result = $this->validateAssignmentValue(
                assignment: $assignment,
                value: $value
            );

            foreach ($result['errors'] as $message) {
                $errors[$field][] = $message;
            }

            if ($result['errors'] !== []) {
                continue;
            }

            $normalizedValues[$code] =
                $result['value'];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(
                $errors
            );
        }

        return $normalizedValues;
    }

    /**
     * Normalize incoming specification keys.
     *
     * Examples:
     *
     * "Screen Size"       becomes "screen_size"
     * "storage-capacity"  becomes "storage_capacity"
     *
     * @param array<string|int, mixed> $specifications
     * @param array<string, array<int, string>> $errors
     *
     * @return array<string, mixed>
     */
    private function normalizeInputKeys(
        array $specifications,
        array &$errors,
        string $attribute
    ): array {
        $normalized = [];

        foreach (
            $specifications as $submittedCode => $value
        ) {
            if (
                !is_string($submittedCode)
                && !is_int($submittedCode)
            ) {
                $errors[$attribute][] =
                    'Every specification must use a valid specification code.';

                continue;
            }

            $code = Str::snake(
                trim((string) $submittedCode)
            );

            if ($code === '') {
                $errors[$attribute][] =
                    'Specification codes must not be empty.';

                continue;
            }

            if (
                array_key_exists(
                    $code,
                    $normalized
                )
            ) {
                $errors["{$attribute}.{$code}"][] =
                    'The specification was submitted more than once.';

                continue;
            }

            $normalized[$code] = $value;
        }

        return $normalized;
    }

    /**
     * Validate one specification assignment value.
     *
     * @return array{
     *     value: mixed,
     *     errors: array<int, string>
     * }
     */
    private function validateAssignmentValue(
        CategorySpecification $assignment,
        mixed $value
    ): array {
        $dataType = $assignment->dataType();

        $typeResult = $this->normalizeTypedValue(
            assignment: $assignment,
            dataType: $dataType,
            value: $value
        );

        if ($typeResult['errors'] !== []) {
            return $typeResult;
        }

        $normalizedValue = $typeResult['value'];

        $configurationErrors =
            $this->validateConfiguredRules(
                assignment: $assignment,
                dataType: $dataType,
                value: $normalizedValue
            );

        return [
            'value' => $normalizedValue,
            'errors' => $configurationErrors,
        ];
    }

    /**
     * Validate and normalize a value according to its data type.
     *
     * @return array{
     *     value: mixed,
     *     errors: array<int, string>
     * }
     */
    private function normalizeTypedValue(
        CategorySpecification $assignment,
        SpecificationDataType $dataType,
        mixed $value
    ): array {
        return match ($dataType) {
            SpecificationDataType::TEXT =>
                $this->normalizeTextValue(
                    $assignment,
                    $value
                ),

            SpecificationDataType::INTEGER =>
                $this->normalizeIntegerValue(
                    $assignment,
                    $value
                ),

            SpecificationDataType::DECIMAL =>
                $this->normalizeDecimalValue(
                    $assignment,
                    $value
                ),

            SpecificationDataType::BOOLEAN =>
                $this->normalizeBooleanValue(
                    $assignment,
                    $value
                ),

            SpecificationDataType::SELECT =>
                $this->normalizeSelectValue(
                    $assignment,
                    $value
                ),

            SpecificationDataType::MULTISELECT =>
                $this->normalizeMultiselectValue(
                    $assignment,
                    $value
                ),

            SpecificationDataType::DATE =>
                $this->normalizeDateValue(
                    $assignment,
                    $value
                ),
        };
    }

    /**
     * Normalize a text specification.
     *
     * @return array{
     *     value: mixed,
     *     errors: array<int, string>
     * }
     */
    private function normalizeTextValue(
        CategorySpecification $assignment,
        mixed $value
    ): array {
        if (!is_string($value)) {
            return $this->invalidTypeResult(
                assignment: $assignment,
                expectedType: 'text'
            );
        }

        return [
            'value' => trim($value),
            'errors' => [],
        ];
    }

    /**
     * Normalize an integer specification.
     *
     * @return array{
     *     value: mixed,
     *     errors: array<int, string>
     * }
     */
    private function normalizeIntegerValue(
        CategorySpecification $assignment,
        mixed $value
    ): array {
        if (is_int($value)) {
            return [
                'value' => $value,
                'errors' => [],
            ];
        }

        if (
            is_string($value)
            && preg_match(
                '/^-?\d+$/',
                trim($value)
            ) === 1
        ) {
            $validated = filter_var(
                trim($value),
                FILTER_VALIDATE_INT
            );

            if ($validated !== false) {
                return [
                    'value' => $validated,
                    'errors' => [],
                ];
            }
        }

        return $this->invalidTypeResult(
            assignment: $assignment,
            expectedType: 'whole number'
        );
    }

    /**
     * Normalize a decimal specification.
     *
     * @return array{
     *     value: mixed,
     *     errors: array<int, string>
     * }
     */
    private function normalizeDecimalValue(
        CategorySpecification $assignment,
        mixed $value
    ): array {
        if (
            !is_int($value)
            && !is_float($value)
            && !(
                is_string($value)
                && is_numeric(trim($value))
            )
        ) {
            return $this->invalidTypeResult(
                assignment: $assignment,
                expectedType: 'number'
            );
        }

        $number = (float) $value;

        if (!is_finite($number)) {
            return [
                'value' => null,
                'errors' => [
                    sprintf(
                        'The %s specification must be a finite number.',
                        $assignment->effectiveLabel()
                    ),
                ],
            ];
        }

        return [
            'value' => $number,
            'errors' => [],
        ];
    }

    /**
     * Normalize a boolean specification.
     *
     * Accepted values:
     *
     * true
     * false
     * 1
     * 0
     * "1"
     * "0"
     *
     * @return array{
     *     value: mixed,
     *     errors: array<int, string>
     * }
     */
    private function normalizeBooleanValue(
        CategorySpecification $assignment,
        mixed $value
    ): array {
        if (is_bool($value)) {
            return [
                'value' => $value,
                'errors' => [],
            ];
        }

        if (
            $value === 1
            || $value === '1'
        ) {
            return [
                'value' => true,
                'errors' => [],
            ];
        }

        if (
            $value === 0
            || $value === '0'
        ) {
            return [
                'value' => false,
                'errors' => [],
            ];
        }

        return $this->invalidTypeResult(
            assignment: $assignment,
            expectedType: 'boolean'
        );
    }

    /**
     * Normalize a single-select specification.
     *
     * The canonical value configured by the administrator is returned.
     *
     * @return array{
     *     value: mixed,
     *     errors: array<int, string>
     * }
     */
    private function normalizeSelectValue(
        CategorySpecification $assignment,
        mixed $value
    ): array {
        if (
            !is_string($value)
            && !is_int($value)
            && !is_float($value)
            && !is_bool($value)
        ) {
            return $this->invalidTypeResult(
                assignment: $assignment,
                expectedType: 'selectable option'
            );
        }

        $optionValues = $assignment
            ->effectiveOptionValues();

        if ($optionValues === []) {
            return [
                'value' => null,
                'errors' => [
                    sprintf(
                        'The %s specification has no configured options.',
                        $assignment->effectiveLabel()
                    ),
                ],
            ];
        }

        $matchedValue = $this
            ->findCanonicalOptionValue(
                value: $value,
                optionValues: $optionValues
            );

        if (!$matchedValue['found']) {
            return [
                'value' => null,
                'errors' => [
                    sprintf(
                        'The selected %s value is invalid.',
                        $assignment->effectiveLabel()
                    ),
                ],
            ];
        }

        return [
            'value' => $matchedValue['value'],
            'errors' => [],
        ];
    }

    /**
     * Normalize a multiselect specification.
     *
     * @return array{
     *     value: mixed,
     *     errors: array<int, string>
     * }
     */
    private function normalizeMultiselectValue(
        CategorySpecification $assignment,
        mixed $value
    ): array {
        if (!is_array($value)) {
            return $this->invalidTypeResult(
                assignment: $assignment,
                expectedType: 'array of selectable options'
            );
        }

        $optionValues = $assignment
            ->effectiveOptionValues();

        if ($optionValues === []) {
            return [
                'value' => null,
                'errors' => [
                    sprintf(
                        'The %s specification has no configured options.',
                        $assignment->effectiveLabel()
                    ),
                ],
            ];
        }

        $normalized = [];
        $seen = [];
        $errors = [];

        foreach ($value as $index => $item) {
            if (
                !is_string($item)
                && !is_int($item)
                && !is_float($item)
                && !is_bool($item)
            ) {
                $errors[] = sprintf(
                    'The %s option at position %d is invalid.',
                    $assignment->effectiveLabel(),
                    $index + 1
                );

                continue;
            }

            $matchedValue = $this
                ->findCanonicalOptionValue(
                    value: $item,
                    optionValues: $optionValues
                );

            if (!$matchedValue['found']) {
                $errors[] = sprintf(
                    'The selected %s option at position %d is invalid.',
                    $assignment->effectiveLabel(),
                    $index + 1
                );

                continue;
            }

            $comparisonKey = $this->comparisonKey(
                $matchedValue['value']
            );

            if (array_key_exists($comparisonKey, $seen)) {
                $errors[] = sprintf(
                    'The %s specification must not contain duplicate options.',
                    $assignment->effectiveLabel()
                );

                continue;
            }

            $seen[$comparisonKey] = true;

            $normalized[] = $matchedValue['value'];
        }

        return [
            'value' => $normalized,
            'errors' => array_values(
                array_unique($errors)
            ),
        ];
    }

    /**
     * Normalize a date specification.
     *
     * @return array{
     *     value: mixed,
     *     errors: array<int, string>
     * }
     */
    private function normalizeDateValue(
        CategorySpecification $assignment,
        mixed $value
    ): array {
        if (!is_string($value)) {
            return $this->invalidTypeResult(
                assignment: $assignment,
                expectedType: 'valid date'
            );
        }

        $value = trim($value);

        $validator = Validator::make(
            [
                'value' => $value,
            ],
            [
                'value' => [
                    'required',
                    'date',
                ],
            ]
        );

        if ($validator->fails()) {
            return $this->invalidTypeResult(
                assignment: $assignment,
                expectedType: 'valid date'
            );
        }

        return [
            'value' => $value,
            'errors' => [],
        ];
    }

    /**
     * Validate category and definition-level configuration.
     *
     * @return array<int, string>
     */
    private function validateConfiguredRules(
        CategorySpecification $assignment,
        SpecificationDataType $dataType,
        mixed $value
    ): array {
        $configuration = $assignment
            ->effectiveValidationConfiguration();

        $errors = [];

        if ($dataType->isNumeric()) {
            $errors = array_merge(
                $errors,
                $this->validateNumericRules(
                    assignment: $assignment,
                    value: (float) $value,
                    configuration: $configuration
                )
            );
        }

        if (
            $dataType ===
            SpecificationDataType::TEXT
        ) {
            $errors = array_merge(
                $errors,
                $this->validateTextRules(
                    assignment: $assignment,
                    value: (string) $value,
                    configuration: $configuration
                )
            );
        }

        if (
            $dataType ===
            SpecificationDataType::MULTISELECT
        ) {
            $errors = array_merge(
                $errors,
                $this->validateCollectionRules(
                    assignment: $assignment,
                    value: $value,
                    configuration: $configuration
                )
            );
        }

        return array_values(
            array_unique($errors)
        );
    }

    /**
     * Validate numeric minimum, maximum and step rules.
     *
     * @param array<string, mixed> $configuration
     *
     * @return array<int, string>
     */
    private function validateNumericRules(
        CategorySpecification $assignment,
        float $value,
        array $configuration
    ): array {
        $errors = [];

        if (
            isset($configuration['min'])
            && is_numeric(
                $configuration['min']
            )
            && $value
                < (float) $configuration['min']
        ) {
            $errors[] = sprintf(
                'The %s specification must be at least %s%s.',
                $assignment->effectiveLabel(),
                $this->formatNumber(
                    $configuration['min']
                ),
                $this->formattedUnit($assignment)
            );
        }

        if (
            isset($configuration['max'])
            && is_numeric(
                $configuration['max']
            )
            && $value
                > (float) $configuration['max']
        ) {
            $errors[] = sprintf(
                'The %s specification must not be greater than %s%s.',
                $assignment->effectiveLabel(),
                $this->formatNumber(
                    $configuration['max']
                ),
                $this->formattedUnit($assignment)
            );
        }

        if (
            isset($configuration['step'])
            && is_numeric(
                $configuration['step']
            )
            && (float) $configuration['step'] > 0
        ) {
            $step = (float) $configuration['step'];

            $origin = isset($configuration['min'])
                && is_numeric($configuration['min'])
                    ? (float) $configuration['min']
                    : 0.0;

            $quotient = ($value - $origin) / $step;

            if (
                abs(
                    $quotient - round($quotient)
                ) > 0.000000001
            ) {
                $errors[] = sprintf(
                    'The %s specification must follow increments of %s%s.',
                    $assignment->effectiveLabel(),
                    $this->formatNumber($step),
                    $this->formattedUnit($assignment)
                );
            }
        }

        return $errors;
    }

    /**
     * Validate text length and pattern rules.
     *
     * @param array<string, mixed> $configuration
     *
     * @return array<int, string>
     */
    private function validateTextRules(
        CategorySpecification $assignment,
        string $value,
        array $configuration
    ): array {
        $errors = [];

        $length = mb_strlen($value);

        if (
            isset($configuration['min_length'])
            && is_numeric(
                $configuration['min_length']
            )
            && $length
                < (int) $configuration['min_length']
        ) {
            $errors[] = sprintf(
                'The %s specification must contain at least %d characters.',
                $assignment->effectiveLabel(),
                (int) $configuration['min_length']
            );
        }

        if (
            isset($configuration['max_length'])
            && is_numeric(
                $configuration['max_length']
            )
            && $length
                > (int) $configuration['max_length']
        ) {
            $errors[] = sprintf(
                'The %s specification must not contain more than %d characters.',
                $assignment->effectiveLabel(),
                (int) $configuration['max_length']
            );
        }

        if (
            isset($configuration['pattern'])
            && is_string(
                $configuration['pattern']
            )
            && trim($configuration['pattern']) !== ''
        ) {
            $matches = $this->matchesPattern(
                value: $value,
                pattern: trim(
                    $configuration['pattern']
                )
            );

            if ($matches === null) {
                $errors[] = sprintf(
                    'The %s specification has an invalid pattern configuration.',
                    $assignment->effectiveLabel()
                );
            } elseif (!$matches) {
                $errors[] = sprintf(
                    'The %s specification has an invalid format.',
                    $assignment->effectiveLabel()
                );
            }
        }

        return $errors;
    }

    /**
     * Validate multiselect item-count rules.
     *
     * @param array<int, mixed> $value
     * @param array<string, mixed> $configuration
     *
     * @return array<int, string>
     */
    private function validateCollectionRules(
        CategorySpecification $assignment,
        array $value,
        array $configuration
    ): array {
        $errors = [];

        $count = count($value);

        if (
            isset($configuration['min_items'])
            && is_numeric(
                $configuration['min_items']
            )
            && $count
                < (int) $configuration['min_items']
        ) {
            $errors[] = sprintf(
                'The %s specification must contain at least %d selected options.',
                $assignment->effectiveLabel(),
                (int) $configuration['min_items']
            );
        }

        if (
            isset($configuration['max_items'])
            && is_numeric(
                $configuration['max_items']
            )
            && $count
                > (int) $configuration['max_items']
        ) {
            $errors[] = sprintf(
                'The %s specification must not contain more than %d selected options.',
                $assignment->effectiveLabel(),
                (int) $configuration['max_items']
            );
        }

        return $errors;
    }

    /**
     * Find the canonical configured option value.
     *
     * Option matching is type-sensitive.
     *
     * The string "16" and integer 16 are treated as different values.
     *
     * @param array<int, mixed> $optionValues
     *
     * @return array{
     *     found: bool,
     *     value: mixed
     * }
     */
    private function findCanonicalOptionValue(
        mixed $value,
        array $optionValues
    ): array {
        $comparisonKey = $this->comparisonKey(
            $value
        );

        foreach ($optionValues as $optionValue) {
            if (
                $comparisonKey
                === $this->comparisonKey(
                    $optionValue
                )
            ) {
                return [
                    'found' => true,
                    'value' => $optionValue,
                ];
            }
        }

        return [
            'found' => false,
            'value' => null,
        ];
    }

    /**
     * Determine whether a value matches a configured regular expression.
     *
     * Both delimited and non-delimited patterns are supported.
     *
     * Examples:
     *
     * ^[A-Za-z0-9 ]+$
     * /^[A-Za-z0-9 ]+$/
     *
     * null means that the configured expression itself is invalid.
     */
    private function matchesPattern(
        string $value,
        string $pattern
    ): ?bool {
        $firstCharacter = $pattern[0] ?? '';

        $appearsDelimited =
            $firstCharacter !== ''
            && !ctype_alnum($firstCharacter)
            && $firstCharacter !== '\\'
            && strrpos(
                $pattern,
                $firstCharacter
            ) !== 0;

        if ($appearsDelimited) {
            $result = @preg_match(
                $pattern,
                $value
            );

            if ($result === false) {
                return null;
            }

            return $result === 1;
        }

        $wrappedPattern = '~'
            .str_replace(
                '~',
                '\~',
                $pattern
            )
            .'~u';

        $result = @preg_match(
            $wrappedPattern,
            $value
        );

        if ($result === false) {
            return null;
        }

        return $result === 1;
    }

    /**
     * Return a standard invalid-type result.
     *
     * @return array{
     *     value: mixed,
     *     errors: array<int, string>
     * }
     */
    private function invalidTypeResult(
        CategorySpecification $assignment,
        string $expectedType
    ): array {
        return [
            'value' => null,

            'errors' => [
                sprintf(
                    'The %s specification must be a valid %s.',
                    $assignment->effectiveLabel(),
                    $expectedType
                ),
            ],
        ];
    }

    /**
     * Determine whether a value is meaningfully present.
     *
     * false and zero are valid specification values.
     */
    private function valueIsPresent(
        mixed $value
    ): bool {
        if ($value === null) {
            return false;
        }

        if (
            is_string($value)
            && trim($value) === ''
        ) {
            return false;
        }

        if (
            is_array($value)
            && $value === []
        ) {
            return false;
        }

        return true;
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
     * Return a formatted optional unit suffix.
     */
    private function formattedUnit(
        CategorySpecification $assignment
    ): string {
        $unit = trim(
            (string) $assignment->unit()
        );

        return $unit === ''
            ? ''
            : " {$unit}";
    }

    /**
     * Format a numeric constraint without unnecessary trailing zeros.
     */
    private function formatNumber(
        int|float|string $value
    ): string {
        if (!is_numeric($value)) {
            return (string) $value;
        }

        $number = (float) $value;

        if (floor($number) === $number) {
            return (string) (int) $number;
        }

        return rtrim(
            rtrim(
                number_format(
                    $number,
                    10,
                    '.',
                    ''
                ),
                '0'
            ),
            '.'
        );
    }
}
