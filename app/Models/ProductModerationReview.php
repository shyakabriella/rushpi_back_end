<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProductModerationAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

class ProductModerationReview extends Model
{
    use HasFactory;

    /**
     * Moderation history only stores created_at.
     */
    public const UPDATED_AT = null;

    /**
     * Fields that may be assigned when recording
     * a product moderation action.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'product_id',
        'reviewed_by',
        'action',
        'reason',
        'internal_notes',
        'product_snapshot',
    ];

    /**
     * Moderation review attribute casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => ProductModerationAction::class,
            'product_snapshot' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    /**
     * Generate the public identifier and protect
     * moderation history from changes.
     */
    protected static function booted(): void
    {
        static::creating(
            function (ProductModerationReview $review): void {
                if (blank($review->public_id)) {
                    $review->public_id = (string) Str::ulid();
                }
            }
        );

        static::updating(function (): never {
            throw new LogicException(
                'Product moderation review records cannot be updated.'
            );
        });

        static::deleting(function (): never {
            throw new LogicException(
                'Product moderation review records cannot be deleted.'
            );
        });
    }

    /**
     * Use public_id for route model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * Product affected by this moderation action.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Administrator who performed the moderation action.
     *
     * This may be null when the seller submits
     * the product for review.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(
            related: User::class,
            foreignKey: 'reviewed_by'
        );
    }

    /**
     * Limit reviews to one product.
     */
    public function scopeForProduct(
        Builder $query,
        int $productId
    ): Builder {
        return $query->where('product_id', $productId);
    }

    /**
     * Limit reviews to one moderation action.
     */
    public function scopeOfAction(
        Builder $query,
        ProductModerationAction|string $action
    ): Builder {
        $value = $action instanceof ProductModerationAction
            ? $action->value
            : $action;

        return $query->where('action', $value);
    }

    /**
     * Limit reviews to actions performed by one user.
     */
    public function scopeReviewedBy(
        Builder $query,
        int $userId
    ): Builder {
        return $query->where('reviewed_by', $userId);
    }

    /**
     * Return moderation history in newest-first order.
     */
    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    /**
     * Determine whether this action requires
     * an administrator.
     */
    public function requiresAdministrator(): bool
    {
        return $this->action->requiresAdministrator();
    }

    /**
     * Determine whether a reason should be recorded.
     */
    public function requiresReason(): bool
    {
        return $this->action->requiresReason();
    }

    /**
     * Determine whether this action made the
     * product publicly visible.
     */
    public function madeProductPublic(): bool
    {
        return $this->action->makesProductPublic();
    }

    /**
     * Determine whether the moderation action
     * was performed automatically by seller submission.
     */
    public function wasSellerSubmission(): bool
    {
        return $this->action === ProductModerationAction::SUBMITTED
            && $this->reviewed_by === null;
    }
}