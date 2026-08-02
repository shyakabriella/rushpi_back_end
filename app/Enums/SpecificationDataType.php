<?php

declare(strict_types=1);

namespace App\Enums;

enum SpecificationDataType: string
{
    case TEXT = 'text';

    case INTEGER = 'integer';

    case DECIMAL = 'decimal';

    case BOOLEAN = 'boolean';

    case SELECT = 'select';

    case MULTISELECT = 'multiselect';

    case DATE = 'date';

    /**
     * Return a human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::TEXT => 'Text',

            self::INTEGER => 'Integer',

            self::DECIMAL => 'Decimal',

            self::BOOLEAN => 'Boolean',

            self::SELECT => 'Single Select',

            self::MULTISELECT => 'Multiple Select',

            self::DATE => 'Date',
        };
    }

    /**
     * Determine whether this type requires selectable options.
     */
    public function usesOptions(): bool
    {
        return match ($this) {
            self::SELECT,
            self::MULTISELECT => true,

            default => false,
        };
    }

    /**
     * Determine whether this type accepts multiple values.
     */
    public function acceptsMultipleValues(): bool
    {
        return $this === self::MULTISELECT;
    }

    /**
     * Determine whether this is a numeric specification type.
     */
    public function isNumeric(): bool
    {
        return match ($this) {
            self::INTEGER,
            self::DECIMAL => true,

            default => false,
        };
    }

    /**
     * Determine whether this is a scalar value type.
     */
    public function isScalar(): bool
    {
        return $this !== self::MULTISELECT;
    }

    /**
     * Return the basic Laravel validation rules for this type.
     *
     * Category-specific and definition-specific validation rules
     * will be added to these rules by the specification validator.
     *
     * @return array<int, string>
     */
    public function validationRules(): array
    {
        return match ($this) {
            self::TEXT => [
                'string',
            ],

            self::INTEGER => [
                'integer',
            ],

            self::DECIMAL => [
                'numeric',
            ],

            self::BOOLEAN => [
                'boolean',
            ],

            self::SELECT => [
                'string',
            ],

            self::MULTISELECT => [
                'array',
            ],

            self::DATE => [
                'date',
            ],
        };
    }

    /**
     * Return the PHP-style value type used by API clients.
     */
    public function apiType(): string
    {
        return match ($this) {
            self::TEXT,
            self::SELECT,
            self::DATE => 'string',

            self::INTEGER => 'integer',

            self::DECIMAL => 'number',

            self::BOOLEAN => 'boolean',

            self::MULTISELECT => 'array',
        };
    }

    /**
     * Return all supported enum values.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $type): string => $type->value,
            self::cases()
        );
    }

    /**
     * Return all supported values with their labels.
     *
     * @return array<int, array<string, string>>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $type): array => [
                'value' => $type->value,
                'label' => $type->label(),
            ],
            self::cases()
        );
    }
}
