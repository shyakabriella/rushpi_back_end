<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductModerationAction: string
{
    case SUBMITTED = 'submitted';

    case REVIEW_STARTED = 'review_started';

    case APPROVED = 'approved';

    case REJECTED = 'rejected';

    case SUSPENDED = 'suspended';

    case RESTORED = 'restored';

    /**
     * Return a readable moderation action.
     */
    public function label(): string
    {
        return match ($this) {
            self::SUBMITTED => 'Submitted for Review',
            self::REVIEW_STARTED => 'Review Started',
            self::APPROVED => 'Product Approved',
            self::REJECTED => 'Product Rejected',
            self::SUSPENDED => 'Product Suspended',
            self::RESTORED => 'Product Restored',
        };
    }

    /**
     * Determine whether this action requires
     * an administrator or super administrator.
     */
    public function requiresAdministrator(): bool
    {
        return in_array($this, [
            self::REVIEW_STARTED,
            self::APPROVED,
            self::REJECTED,
            self::SUSPENDED,
            self::RESTORED,
        ], true);
    }

    /**
     * Determine whether a reason should be provided.
     */
    public function requiresReason(): bool
    {
        return in_array($this, [
            self::REJECTED,
            self::SUSPENDED,
        ], true);
    }

    /**
     * Determine whether this action makes
     * the product publicly visible.
     */
    public function makesProductPublic(): bool
    {
        return in_array($this, [
            self::APPROVED,
            self::RESTORED,
        ], true);
    }

    /**
     * Return all moderation action values.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $action): string => $action->value,
            self::cases()
        );
    }
}
