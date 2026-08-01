<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductStatus: string
{
    case DRAFT = 'draft';

    case PENDING_REVIEW = 'pending_review';

    case APPROVED = 'approved';

    case REJECTED = 'rejected';

    case SUSPENDED = 'suspended';

    case ARCHIVED = 'archived';

    /**
     * Return a readable product status.
     */
    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::PENDING_REVIEW => 'Pending Review',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            self::SUSPENDED => 'Suspended',
            self::ARCHIVED => 'Archived',
        };
    }

    /**
     * Determine whether the product can appear publicly.
     */
    public function isPubliclyVisible(): bool
    {
        return $this === self::APPROVED;
    }

    /**
     * Determine whether the seller can edit the product.
     */
    public function canBeEditedBySeller(): bool
    {
        return in_array($this, [
            self::DRAFT,
            self::REJECTED,
            self::APPROVED,
        ], true);
    }

    /**
     * Determine whether the product is waiting for admin review.
     */
    public function isPendingReview(): bool
    {
        return $this === self::PENDING_REVIEW;
    }

    /**
     * Return all status values.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $status): string => $status->value,
            self::cases()
        );
    }
}
