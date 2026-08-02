<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductModerationAction: string
{
    /*
    |--------------------------------------------------------------------------
    | Existing lifecycle audit actions
    |--------------------------------------------------------------------------
    |
    | These values are preserved for compatibility with existing database
    | records and earlier catalog workflow implementation.
    |
    */

    case SUBMITTED =
        'submitted';

    case REVIEW_STARTED =
        'review_started';

    case APPROVED =
        'approved';

    case REJECTED =
        'rejected';

    case SUSPENDED =
        'suspended';

    case RESTORED =
        'restored';

    /*
    |--------------------------------------------------------------------------
    | Administrator API commands
    |--------------------------------------------------------------------------
    |
    | These are the values accepted by:
    |
    | POST /api/admin/products/{product}/moderate
    |
    */

    case APPROVE =
        'approve';

    case REJECT =
        'reject';

    case SUSPEND =
        'suspend';

    case RETURN_TO_DRAFT =
        'return_to_draft';

    /**
     * Return a readable moderation action.
     */
    public function label(): string
    {
        return match ($this) {
            self::SUBMITTED =>
                'Submitted for Review',

            self::REVIEW_STARTED =>
                'Review Started',

            self::APPROVED =>
                'Product Approved',

            self::REJECTED =>
                'Product Rejected',

            self::SUSPENDED =>
                'Product Suspended',

            self::RESTORED =>
                'Product Restored',

            self::APPROVE =>
                'Approve Product',

            self::REJECT =>
                'Reject Product',

            self::SUSPEND =>
                'Suspend Product',

            self::RETURN_TO_DRAFT =>
                'Return Product to Draft',
        };
    }

    /**
     * Determine whether this action requires an administrator.
     */
    public function requiresAdministrator(): bool
    {
        return in_array(
            $this,
            [
                self::REVIEW_STARTED,
                self::APPROVED,
                self::REJECTED,
                self::SUSPENDED,
                self::RESTORED,
                self::APPROVE,
                self::REJECT,
                self::SUSPEND,
                self::RETURN_TO_DRAFT,
            ],
            true
        );
    }

    /**
     * Determine whether this action requires a reason.
     */
    public function requiresReason(): bool
    {
        return in_array(
            $this,
            [
                self::REJECTED,
                self::SUSPENDED,
                self::REJECT,
                self::SUSPEND,
                self::RETURN_TO_DRAFT,
            ],
            true
        );
    }

    /**
     * Determine whether this action makes the product publicly visible.
     */
    public function makesProductPublic(): bool
    {
        return in_array(
            $this,
            [
                self::APPROVED,
                self::RESTORED,
                self::APPROVE,
            ],
            true
        );
    }

    /**
     * Determine whether this value represents an incoming API command.
     */
    public function isCommand(): bool
    {
        return in_array(
            $this,
            [
                self::APPROVE,
                self::REJECT,
                self::SUSPEND,
                self::RETURN_TO_DRAFT,
            ],
            true
        );
    }

    /**
     * Determine whether this value represents an audit-history event.
     */
    public function isAuditEvent(): bool
    {
        return !$this->isCommand();
    }

    /**
     * Return the resulting audit action where applicable.
     */
    public function auditAction(): self
    {
        return match ($this) {
            self::APPROVE =>
                self::APPROVED,

            self::REJECT =>
                self::REJECTED,

            self::SUSPEND =>
                self::SUSPENDED,

            self::RETURN_TO_DRAFT =>
                self::RESTORED,

            default =>
                $this,
        };
    }

    /**
     * Return values accepted by the moderation API.
     *
     * @return array<int, string>
     */
    public static function commandValues(): array
    {
        return [
            self::APPROVE->value,
            self::REJECT->value,
            self::SUSPEND->value,
            self::RETURN_TO_DRAFT->value,
        ];
    }

    /**
     * Return existing audit-event values.
     *
     * @return array<int, string>
     */
    public static function auditValues(): array
    {
        return [
            self::SUBMITTED->value,
            self::REVIEW_STARTED->value,
            self::APPROVED->value,
            self::REJECTED->value,
            self::SUSPENDED->value,
            self::RESTORED->value,
        ];
    }

    /**
     * Return all moderation action values.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (
                self $action
            ): string =>
                $action->value,
            self::cases()
        );
    }
}