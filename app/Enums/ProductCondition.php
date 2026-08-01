<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductCondition: string
{
    case NEW = 'new';

    case REFURBISHED = 'refurbished';

    case USED_LIKE_NEW = 'used_like_new';

    case USED_GOOD = 'used_good';

    case USED_FAIR = 'used_fair';

    /**
     * Return a readable product condition.
     */
    public function label(): string
    {
        return match ($this) {
            self::NEW => 'New',
            self::REFURBISHED => 'Refurbished',
            self::USED_LIKE_NEW => 'Used - Like New',
            self::USED_GOOD => 'Used - Good',
            self::USED_FAIR => 'Used - Fair',
        };
    }

    /**
     * Determine whether the condition represents
     * a previously used product.
     */
    public function isUsed(): bool
    {
        return in_array($this, [
            self::USED_LIKE_NEW,
            self::USED_GOOD,
            self::USED_FAIR,
        ], true);
    }

    /**
     * Determine whether warranty information
     * should normally be provided.
     */
    public function shouldHaveWarranty(): bool
    {
        return in_array($this, [
            self::NEW,
            self::REFURBISHED,
        ], true);
    }

    /**
     * Return all condition values.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $condition): string => $condition->value,
            self::cases()
        );
    }
}
