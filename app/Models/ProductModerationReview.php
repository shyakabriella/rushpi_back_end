<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProductModerationAction;
use App\Enums\ProductModerationFlag;
use App\Enums\ProductStatus;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class ProductModerationReview extends Model
{
    use HasFactory;
    use HasUlids;

    /**
     * Cached moderator foreign-key column.
     */
    private static ?string $resolvedModeratorForeignKey =
        null;

    /**
     * Database table used by this model.
     */
    protected $table =
        'product_moderation_reviews';

    /**
     * Mass-assignable attributes.
     *
     * Some moderator-column alternatives are included for compatibility with
     * existing project migrations. Only columns supplied by the application
     * will be written.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'product_id',
        'moderator_id',
        'moderated_by',
        'reviewer_id',
        'reviewed_by',
        'admin_user_id',
        'created_by',

        'action',
        'from_status',
        'to_status',

        'reason',
        'notes',

        'moderation_flags',
        'is_prohibited_item',
        'flag_notes',
        'flagged_at',

        'metadata',
    ];

    /**
     * Internal database identifiers hidden during serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'id',
        'product_id',
        'moderator_id',
        'moderated_by',
        'reviewer_id',
        'reviewed_by',
        'admin_user_id',
        'created_by',
    ];

    /**
     * Generate ULIDs for the public identifier.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return [
            'public_id',
        ];
    }

    /**
     * Use the public ULID for route-model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * Model attribute casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' =>
                ProductModerationAction::class,

            'from_status' =>
                ProductStatus::class,

            'to_status' =>
                ProductStatus::class,

            'moderation_flags' =>
                'array',

            'is_prohibited_item' =>
                'boolean',

            'metadata' =>
                'array',

            'flagged_at' =>
                'immutable_datetime',

            'created_at' =>
                'immutable_datetime',

            'updated_at' =>
                'immutable_datetime',
        ];
    }

    /**
     * Normalize moderation information before persistence.
     */
    protected static function booted(): void
    {
        static::saving(
            function (
                self $review
            ): void {
                $review->reason =
                    self::nullableTrimmedText(
                        $review->reason,
                        5000
                    );

                $review->notes =
                    self::nullableTrimmedText(
                        $review->notes,
                        10000
                    );

                $review->flag_notes =
                    self::nullableTrimmedText(
                        $review->flag_notes,
                        10000
                    );

                $flags =
                    self::normalizeFlagValues(
                        $review->moderation_flags
                            ?? []
                    );

                $review->moderation_flags =
                    $flags !== []
                        ? $flags
                        : null;

                $review->is_prohibited_item =
                    self::containsProhibitedFlag(
                        $flags
                    );

                if ($flags !== []) {
                    $review->flagged_at =
                        $review->flagged_at
                        ?? now();
                } else {
                    $review->flagged_at =
                        null;
                }

                if (
                    is_array($review->metadata)
                ) {
                    $review->metadata =
                        self::normalizeMetadata(
                            $review->metadata
                        );
                }
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Product affected by this moderation review.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(
            Product::class
        );
    }

    /**
     * Administrator or moderator who performed the review.
     */
    public function moderator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            $this->moderatorForeignKey()
        );
    }

    /**
     * Compatibility alias for moderator().
     */
    public function reviewer(): BelongsTo
    {
        return $this->moderator();
    }

    /*
    |--------------------------------------------------------------------------
    | Query scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Filter moderation history by product.
     *
     * @param Builder<ProductModerationReview> $query
     */
    public function scopeForProduct(
        Builder $query,
        Product|int $product
    ): Builder {
        $productId = $product
            instanceof Product
                ? $product->getKey()
                : $product;

        return $query->where(
            'product_id',
            $productId
        );
    }

    /**
     * Filter reviews containing moderation flags.
     *
     * @param Builder<ProductModerationReview> $query
     */
    public function scopeFlagged(
        Builder $query
    ): Builder {
        return $query
            ->whereNotNull(
                'moderation_flags'
            )
            ->whereNotNull(
                'flagged_at'
            );
    }

    /**
     * Filter reviews classified as prohibited items.
     *
     * @param Builder<ProductModerationReview> $query
     */
    public function scopeProhibited(
        Builder $query
    ): Builder {
        return $query->where(
            'is_prohibited_item',
            true
        );
    }

    /**
     * Filter reviews by moderation action.
     *
     * @param Builder<ProductModerationReview> $query
     */
    public function scopeWithAction(
        Builder $query,
        ProductModerationAction|string $action
    ): Builder {
        $actionValue = $action
            instanceof ProductModerationAction
                ? $action->value
                : trim($action);

        return $query->where(
            'action',
            $actionValue
        );
    }

    /**
     * Apply chronological audit-history ordering.
     *
     * @param Builder<ProductModerationReview> $query
     */
    public function scopeChronological(
        Builder $query
    ): Builder {
        return $query
            ->orderBy('created_at')
            ->orderBy('id');
    }

    /**
     * Apply newest-first audit-history ordering.
     *
     * @param Builder<ProductModerationReview> $query
     */
    public function scopeLatestFirst(
        Builder $query
    ): Builder {
        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    /*
    |--------------------------------------------------------------------------
    | Moderation action and status
    |--------------------------------------------------------------------------
    */

    /**
     * Return the moderation action scalar.
     */
    public function actionValue(): ?string
    {
        return $this->enumValue(
            $this->action
        );
    }

    /**
     * Return the previous product status scalar.
     */
    public function fromStatusValue(): ?string
    {
        return $this->enumValue(
            $this->from_status
        );
    }

    /**
     * Return the resulting product status scalar.
     */
    public function toStatusValue(): ?string
    {
        return $this->enumValue(
            $this->to_status
        );
    }

    /**
     * Determine whether this review approved the product.
     */
    public function approvedProduct(): bool
    {
        return $this->toStatusValue()
            === ProductStatus::APPROVED->value;
    }

    /**
     * Determine whether this review rejected the product.
     */
    public function rejectedProduct(): bool
    {
        return $this->toStatusValue()
            === ProductStatus::REJECTED->value;
    }

    /**
     * Determine whether this review suspended the product.
     */
    public function suspendedProduct(): bool
    {
        return $this->toStatusValue()
            === ProductStatus::SUSPENDED->value;
    }

    /*
    |--------------------------------------------------------------------------
    | Structured moderation flags
    |--------------------------------------------------------------------------
    */

    /**
     * Return normalized moderation-flag enum instances.
     *
     * @return array<int, ProductModerationFlag>
     */
    public function moderationFlags(): array
    {
        $flags =
            self::normalizeFlagValues(
                $this->moderation_flags
                    ?? []
            );

        return array_values(
            array_filter(
                array_map(
                    static fn (
                        string $flag
                    ): ?ProductModerationFlag =>
                        ProductModerationFlag
                            ::tryFrom($flag),
                    $flags
                )
            )
        );
    }

    /**
     * Return normalized moderation-flag values.
     *
     * @return array<int, string>
     */
    public function moderationFlagValues(): array
    {
        return array_map(
            static fn (
                ProductModerationFlag $flag
            ): string =>
                $flag->value,
            $this->moderationFlags()
        );
    }

    /**
     * Return moderation flags with labels and behavior metadata.
     *
     * @return array<int, array{
     *     value: string,
     *     label: string,
     *     is_prohibited: bool,
     *     requires_rejection: bool,
     *     is_correctable: bool
     * }>
     */
    public function moderationFlagDetails(): array
    {
        return array_map(
            static fn (
                ProductModerationFlag $flag
            ): array => [
                'value' =>
                    $flag->value,

                'label' =>
                    $flag->label(),

                'is_prohibited' =>
                    $flag->isProhibited(),

                'requires_rejection' =>
                    $flag->requiresRejection(),

                'is_correctable' =>
                    $flag->isCorrectable(),
            ],
            $this->moderationFlags()
        );
    }

    /**
     * Determine whether this review contains a specific flag.
     */
    public function hasFlag(
        ProductModerationFlag|string $flag
    ): bool {
        $value = $flag
            instanceof ProductModerationFlag
                ? $flag->value
                : trim($flag);

        return in_array(
            $value,
            $this->moderationFlagValues(),
            true
        );
    }

    /**
     * Determine whether this review contains any moderation flag.
     */
    public function hasModerationFlags(): bool
    {
        return $this->moderationFlagValues()
            !== [];
    }

    /**
     * Determine whether the product was classified as prohibited.
     */
    public function isProhibitedItem(): bool
    {
        if (
            (bool) $this->is_prohibited_item
        ) {
            return true;
        }

        return collect(
            $this->moderationFlags()
        )->contains(
            static fn (
                ProductModerationFlag $flag
            ): bool =>
                $flag->isProhibited()
        );
    }

    /**
     * Determine whether the selected flags require rejection.
     */
    public function requiresRejection(): bool
    {
        if ($this->isProhibitedItem()) {
            return true;
        }

        return collect(
            $this->moderationFlags()
        )->contains(
            static fn (
                ProductModerationFlag $flag
            ): bool =>
                $flag->requiresRejection()
        );
    }

    /**
     * Determine whether at least one selected issue can be corrected.
     */
    public function hasCorrectableFlags(): bool
    {
        return collect(
            $this->moderationFlags()
        )->contains(
            static fn (
                ProductModerationFlag $flag
            ): bool =>
                $flag->isCorrectable()
        );
    }

    /**
     * Replace moderation flags with normalized values.
     *
     * @param iterable<int, ProductModerationFlag|string> $flags
     */
    public function setModerationFlags(
        iterable $flags
    ): self {
        $this->moderation_flags =
            self::normalizeFlagValues(
                $flags
            );

        return $this;
    }

    /**
     * Add one moderation flag.
     */
    public function addModerationFlag(
        ProductModerationFlag|string $flag
    ): self {
        $flags =
            $this->moderationFlagValues();

        $flags[] = $flag
            instanceof ProductModerationFlag
                ? $flag->value
                : $flag;

        return $this->setModerationFlags(
            $flags
        );
    }

    /**
     * Remove one moderation flag.
     */
    public function removeModerationFlag(
        ProductModerationFlag|string $flag
    ): self {
        $value = $flag
            instanceof ProductModerationFlag
                ? $flag->value
                : trim($flag);

        return $this->setModerationFlags(
            array_values(
                array_filter(
                    $this
                        ->moderationFlagValues(),
                    static fn (
                        string $current
                    ): bool =>
                        $current !== $value
                )
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Audit output
    |--------------------------------------------------------------------------
    */

    /**
     * Return moderation-history information suitable for API resources.
     *
     * @return array<string, mixed>
     */
    public function toAuditData(): array
    {
        return [
            'public_id' =>
                (string) $this->public_id,

            'action' =>
                $this->actionValue(),

            'from_status' =>
                $this->fromStatusValue(),

            'to_status' =>
                $this->toStatusValue(),

            'reason' =>
                $this->reason,

            'notes' =>
                $this->notes,

            'moderation_flags' =>
                $this->moderationFlagDetails(),

            'is_prohibited_item' =>
                $this->isProhibitedItem(),

            'requires_rejection' =>
                $this->requiresRejection(),

            'has_correctable_flags' =>
                $this->hasCorrectableFlags(),

            'flag_notes' =>
                $this->flag_notes,

            'flagged_at' =>
                $this->flagged_at
                    ?->toISOString(),

            'created_at' =>
                $this->created_at
                    ?->toISOString(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Internal normalization helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Normalize moderation flag input.
     *
     * @param iterable<int, ProductModerationFlag|string>|mixed $flags
     *
     * @return array<int, string>
     */
    private static function normalizeFlagValues(
        mixed $flags
    ): array {
        if ($flags === null) {
            return [];
        }

        if (
            is_string($flags)
            || $flags instanceof BackedEnum
        ) {
            $flags = [
                $flags,
            ];
        }

        if (!is_iterable($flags)) {
            return [];
        }

        $normalized = [];

        foreach ($flags as $flag) {
            $value = $flag
                instanceof ProductModerationFlag
                    ? $flag->value
                    : (
                        $flag instanceof BackedEnum
                            ? (string) $flag->value
                            : trim((string) $flag)
                    );

            $moderationFlag =
                ProductModerationFlag
                    ::tryFrom($value);

            if (
                !$moderationFlag instanceof
                ProductModerationFlag
            ) {
                continue;
            }

            $normalized[] =
                $moderationFlag->value;
        }

        return array_values(
            array_unique($normalized)
        );
    }

    /**
     * Determine whether normalized flags contain a prohibited classification.
     *
     * @param array<int, string> $flags
     */
    private static function containsProhibitedFlag(
        array $flags
    ): bool {
        foreach ($flags as $flagValue) {
            $flag =
                ProductModerationFlag
                    ::tryFrom($flagValue);

            if (
                $flag instanceof
                    ProductModerationFlag
                && $flag->isProhibited()
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Normalize optional text while enforcing a database-safe maximum.
     */
    private static function nullableTrimmedText(
        mixed $value,
        int $maximumLength
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value = trim(
            (string) $value
        );

        if ($value === '') {
            return null;
        }

        return Str::limit(
            $value,
            $maximumLength,
            ''
        );
    }

    /**
     * Normalize metadata recursively enough for JSON storage.
     *
     * @param array<string, mixed> $metadata
     *
     * @return array<string, mixed>
     */
    private static function normalizeMetadata(
        array $metadata
    ): array {
        return collect($metadata)
            ->map(
                static function (
                    mixed $value
                ): mixed {
                    if ($value instanceof BackedEnum) {
                        return $value->value;
                    }

                    return $value;
                }
            )
            ->all();
    }

    /**
     * Convert an enum or scalar value to a nullable string.
     */
    private function enumValue(
        mixed $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        $value = trim(
            (string) $value
        );

        return $value !== ''
            ? $value
            : null;
    }

    /**
     * Resolve the moderator foreign-key column used by the current schema.
     */
    private function moderatorForeignKey(): string
    {
        if (
            self::$resolvedModeratorForeignKey
            !== null
        ) {
            return self::$resolvedModeratorForeignKey;
        }

        $candidates = [
            'moderator_id',
            'moderated_by',
            'reviewer_id',
            'reviewed_by',
            'admin_user_id',
            'created_by',
        ];

        foreach ($candidates as $column) {
            if (
                Schema::hasColumn(
                    $this->getTable(),
                    $column
                )
            ) {
                self::$resolvedModeratorForeignKey =
                    $column;

                return $column;
            }
        }

        self::$resolvedModeratorForeignKey =
            'moderator_id';

        return self::$resolvedModeratorForeignKey;
    }
}