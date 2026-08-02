<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Support\Str;

final class ProductReturnPolicy extends Model
{
    use HasFactory;
    use HasUlids;

    /**
     * Supported return-shipping payers.
     */
    public const SHIPPING_PAYER_CUSTOMER = 'customer';

    public const SHIPPING_PAYER_SELLER = 'seller';

    public const SHIPPING_PAYER_PLATFORM = 'platform';

    public const SHIPPING_PAYER_CONDITIONAL = 'conditional';

    /**
     * Common accepted return conditions.
     */
    public const CONDITION_UNUSED = 'unused';

    public const CONDITION_UNOPENED = 'unopened';

    public const CONDITION_DEFECTIVE = 'defective';

    public const CONDITION_DAMAGED = 'damaged';

    public const CONDITION_WRONG_ITEM = 'wrong_item';

    public const CONDITION_NOT_AS_DESCRIBED = 'not_as_described';

    /**
     * Supported refund methods.
     */
    public const REFUND_ORIGINAL_PAYMENT_METHOD =
        'original_payment_method';

    public const REFUND_WALLET_CREDIT =
        'wallet_credit';

    public const REFUND_BANK_TRANSFER =
        'bank_transfer';

    public const REFUND_MOBILE_MONEY =
        'mobile_money';

    /**
     * Mass-assignable fields.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'product_id',
        'is_returnable',
        'return_window_days',
        'allow_refund',
        'allow_exchange',
        'requires_original_packaging',
        'requires_proof_of_purchase',
        'restocking_fee_percent',
        'return_shipping_payer',
        'accepted_conditions',
        'refund_methods',
        'instructions',
        'non_returnable_reason',
        'is_active',
        'created_by',
        'updated_by',
    ];

    /**
     * Model casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_returnable' =>
                'boolean',

            'return_window_days' =>
                'integer',

            'allow_refund' =>
                'boolean',

            'allow_exchange' =>
                'boolean',

            'requires_original_packaging' =>
                'boolean',

            'requires_proof_of_purchase' =>
                'boolean',

            'restocking_fee_percent' =>
                'decimal:2',

            'accepted_conditions' =>
                'array',

            'refund_methods' =>
                'array',

            'is_active' =>
                'boolean',

            'created_at' =>
                'datetime',

            'updated_at' =>
                'datetime',
        ];
    }

    /**
     * Generate a ULID for the public identifier while keeping the numeric
     * primary key.
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
     * Normalize policy data before saving.
     */
    protected static function booted(): void
    {
        static::saving(
            static function (
                ProductReturnPolicy $policy
            ): void {
                $policy->return_shipping_payer =
                    strtolower(
                        trim(
                            (string) (
                                $policy
                                    ->return_shipping_payer
                                ?: self::SHIPPING_PAYER_CUSTOMER
                            )
                        )
                    );

                $policy->accepted_conditions =
                    self::normalizeStringList(
                        $policy->accepted_conditions
                    );

                $policy->refund_methods =
                    self::normalizeStringList(
                        $policy->refund_methods
                    );

                $policy->instructions =
                    self::nullableTrim(
                        $policy->instructions
                    );

                $policy->non_returnable_reason =
                    self::nullableTrim(
                        $policy->non_returnable_reason
                    );

                /*
                 * Non-returnable products cannot offer a refund or exchange.
                 */

                if (!$policy->is_returnable) {
                    $policy->return_window_days =
                        null;

                    $policy->allow_refund =
                        false;

                    $policy->allow_exchange =
                        false;

                    $policy->refund_methods =
                        null;

                    $policy->accepted_conditions =
                        null;

                    $policy->restocking_fee_percent =
                        0;
                } else {
                    /*
                     * A returnable product defaults to a seven-day window
                     * when no window was provided.
                     */

                    if (
                        $policy->return_window_days
                        === null
                    ) {
                        $policy->return_window_days =
                            7;
                    }

                    $policy->non_returnable_reason =
                        null;
                }

                /*
                 * Refund methods are irrelevant when refunds are disabled.
                 */

                if (!$policy->allow_refund) {
                    $policy->refund_methods =
                        null;
                }
            }
        );
    }

    /**
     * Product governed by this policy.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(
            Product::class
        );
    }

    /**
     * User who created this policy.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    /**
     * User who last updated this policy.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'updated_by'
        );
    }

    /**
     * Scope active return policies.
     */
    public function scopeActive(
        Builder $query
    ): Builder {
        return $query->where(
            'is_active',
            true
        );
    }

    /**
     * Scope policies that allow returns.
     */
    public function scopeReturnable(
        Builder $query
    ): Builder {
        return $query
            ->where(
                'is_active',
                true
            )
            ->where(
                'is_returnable',
                true
            );
    }

    /**
     * Scope policies for one product.
     */
    public function scopeForProduct(
        Builder $query,
        Product|int $product
    ): Builder {
        $productId = $product instanceof Product
            ? $product->getKey()
            : $product;

        return $query->where(
            'product_id',
            $productId
        );
    }

    /**
     * Determine whether the policy currently allows returns.
     */
    public function allowsReturns(): bool
    {
        return $this->is_active
            && $this->is_returnable
            && $this->return_window_days !== null
            && $this->return_window_days > 0;
    }

    /**
     * Determine whether refunds are available.
     */
    public function allowsRefunds(): bool
    {
        return $this->allowsReturns()
            && $this->allow_refund;
    }

    /**
     * Determine whether exchanges are available.
     */
    public function allowsExchanges(): bool
    {
        return $this->allowsReturns()
            && $this->allow_exchange;
    }

    /**
     * Determine whether a restocking fee applies.
     */
    public function hasRestockingFee(): bool
    {
        return $this->allowsReturns()
            && $this->restockingFeePercent() > 0;
    }

    /**
     * Return the restocking fee as a float.
     */
    public function restockingFeePercent(): float
    {
        return (float) (
            $this->restocking_fee_percent ?? 0
        );
    }

    /**
     * Calculate the monetary restocking fee.
     */
    public function calculateRestockingFee(
        int|float|string $amount
    ): float {
        if (
            !$this->hasRestockingFee()
            || !is_numeric($amount)
        ) {
            return 0.0;
        }

        return round(
            (float) $amount
            * ($this->restockingFeePercent() / 100),
            2
        );
    }

    /**
     * Return the deadline for requesting a return.
     */
    public function returnDeadline(
        CarbonInterface $fulfilledAt
    ): ?CarbonImmutable {
        if (!$this->allowsReturns()) {
            return null;
        }

        return CarbonImmutable::instance(
            $fulfilledAt
        )->addDays(
            (int) $this->return_window_days
        );
    }

    /**
     * Determine whether a return request is inside the configured window.
     */
    public function isWithinReturnWindow(
        CarbonInterface $fulfilledAt,
        ?CarbonInterface $checkedAt = null
    ): bool {
        $deadline = $this->returnDeadline(
            $fulfilledAt
        );

        if ($deadline === null) {
            return false;
        }

        $fulfilledMoment =
            CarbonImmutable::instance(
                $fulfilledAt
            );

        $checkedMoment =
            $checkedAt instanceof CarbonInterface
                ? CarbonImmutable::instance(
                    $checkedAt
                )
                : CarbonImmutable::now();

        return $checkedMoment
            ->greaterThanOrEqualTo(
                $fulfilledMoment
            )
            && $checkedMoment
                ->lessThanOrEqualTo(
                    $deadline
                );
    }

    /**
     * Determine whether a submitted return condition is accepted.
     */
    public function acceptsCondition(
        string $condition
    ): bool {
        $condition = Str::snake(
            trim($condition)
        );

        return in_array(
            $condition,
            $this->acceptedConditions(),
            true
        );
    }

    /**
     * Determine whether a refund method is supported.
     */
    public function supportsRefundMethod(
        string $method
    ): bool {
        if (!$this->allowsRefunds()) {
            return false;
        }

        $method = Str::snake(
            trim($method)
        );

        return in_array(
            $method,
            $this->refundMethods(),
            true
        );
    }

    /**
     * Return normalized accepted conditions.
     *
     * @return array<int, string>
     */
    public function acceptedConditions(): array
    {
        return self::normalizeStringList(
            $this->accepted_conditions
        ) ?? [];
    }

    /**
     * Return normalized refund methods.
     *
     * @return array<int, string>
     */
    public function refundMethods(): array
    {
        return self::normalizeStringList(
            $this->refund_methods
        ) ?? [];
    }

    /**
     * Return all supported shipping-payer values.
     *
     * @return array<int, string>
     */
    public static function shippingPayers(): array
    {
        return [
            self::SHIPPING_PAYER_CUSTOMER,
            self::SHIPPING_PAYER_SELLER,
            self::SHIPPING_PAYER_PLATFORM,
            self::SHIPPING_PAYER_CONDITIONAL,
        ];
    }

    /**
     * Return all supported common return conditions.
     *
     * @return array<int, string>
     */
    public static function commonConditions(): array
    {
        return [
            self::CONDITION_UNUSED,
            self::CONDITION_UNOPENED,
            self::CONDITION_DEFECTIVE,
            self::CONDITION_DAMAGED,
            self::CONDITION_WRONG_ITEM,
            self::CONDITION_NOT_AS_DESCRIBED,
        ];
    }

    /**
     * Return all supported refund methods.
     *
     * @return array<int, string>
     */
    public static function supportedRefundMethods(): array
    {
        return [
            self::REFUND_ORIGINAL_PAYMENT_METHOD,
            self::REFUND_WALLET_CREDIT,
            self::REFUND_BANK_TRANSFER,
            self::REFUND_MOBILE_MONEY,
        ];
    }

    /**
     * Return human-readable shipping responsibility.
     */
    public function shippingPayerLabel(): string
    {
        return match (
            $this->return_shipping_payer
        ) {
            self::SHIPPING_PAYER_SELLER =>
                'Seller',

            self::SHIPPING_PAYER_PLATFORM =>
                'RushPi platform',

            self::SHIPPING_PAYER_CONDITIONAL =>
                'Depends on the return reason',

            default =>
                'Customer',
        };
    }

    /**
     * Return policy configuration errors.
     *
     * @return array<string, array<int, string>>
     */
    public function configurationErrors(): array
    {
        $errors = [];

        if (
            !in_array(
                $this->return_shipping_payer,
                self::shippingPayers(),
                true
            )
        ) {
            $errors['return_shipping_payer'][] =
                'The return shipping payer is invalid.';
        }

        $restockingFee =
            $this->restockingFeePercent();

        if (
            $restockingFee < 0
            || $restockingFee > 100
        ) {
            $errors['restocking_fee_percent'][] =
                'The restocking fee must be between 0 and 100 percent.';
        }

        if ($this->is_returnable) {
            if (
                $this->return_window_days === null
                || $this->return_window_days < 1
            ) {
                $errors['return_window_days'][] =
                    'A returnable product must have a return window of at least one day.';
            }

            if (
                !$this->allow_refund
                && !$this->allow_exchange
            ) {
                $errors['resolution'][] =
                    'A returnable product must allow a refund, an exchange, or both.';
            }

            if (
                $this->allow_refund
                && $this->refundMethods() === []
            ) {
                $errors['refund_methods'][] =
                    'At least one refund method is required when refunds are allowed.';
            }
        } elseif (
            trim(
                (string) $this
                    ->non_returnable_reason
            ) === ''
        ) {
            $errors['non_returnable_reason'][] =
                'A reason is required for a non-returnable product.';
        }

        return $errors;
    }

    /**
     * Determine whether the policy configuration is complete.
     */
    public function hasValidConfiguration(): bool
    {
        return $this->configurationErrors()
            === [];
    }

    /**
     * Return customer-safe policy information.
     *
     * @return array<string, mixed>
     */
    public function toCustomerPolicy(): array
    {
        return [
            'public_id' =>
                (string) $this->public_id,

            'is_active' =>
                (bool) $this->is_active,

            'is_returnable' =>
                (bool) $this->is_returnable,

            'return_window_days' =>
                $this->is_returnable
                    ? $this->return_window_days
                    : null,

            'resolutions' => [
                'refund' =>
                    $this->allowsRefunds(),

                'exchange' =>
                    $this->allowsExchanges(),
            ],

            'requirements' => [
                'original_packaging' =>
                    $this->is_returnable
                    && $this
                        ->requires_original_packaging,

                'proof_of_purchase' =>
                    $this->is_returnable
                    && $this
                        ->requires_proof_of_purchase,
            ],

            'restocking_fee_percent' =>
                $this->is_returnable
                    ? $this
                        ->restockingFeePercent()
                    : 0,

            'return_shipping' => [
                'payer' =>
                    $this->return_shipping_payer,

                'label' =>
                    $this->shippingPayerLabel(),
            ],

            'accepted_conditions' =>
                collect(
                    $this->acceptedConditions()
                )
                    ->map(
                        static fn (
                            string $condition
                        ): array => [
                            'value' =>
                                $condition,

                            'label' =>
                                Str::headline(
                                    $condition
                                ),
                        ]
                    )
                    ->values()
                    ->all(),

            'refund_methods' =>
                collect(
                    $this->refundMethods()
                )
                    ->map(
                        static fn (
                            string $method
                        ): array => [
                            'value' =>
                                $method,

                            'label' =>
                                Str::headline(
                                    $method
                                ),
                        ]
                    )
                    ->values()
                    ->all(),

            'instructions' =>
                $this->instructions,

            'non_returnable_reason' =>
                !$this->is_returnable
                    ? $this
                        ->non_returnable_reason
                    : null,
        ];
    }

    /**
     * Normalize a nullable list of string values.
     *
     * @return array<int, string>|null
     */
    private static function normalizeStringList(
        mixed $values
    ): ?array {
        if (!is_array($values)) {
            return null;
        }

        $normalized = collect($values)
            ->filter(
                static fn (
                    mixed $value
                ): bool => is_string($value)
                    || is_int($value)
            )
            ->map(
                static fn (
                    string|int $value
                ): string => Str::snake(
                    trim((string) $value)
                )
            )
            ->filter(
                static fn (
                    string $value
                ): bool => $value !== ''
            )
            ->unique()
            ->values()
            ->all();

        return $normalized !== []
            ? $normalized
            : null;
    }

    /**
     * Trim a nullable text value.
     */
    private static function nullableTrim(
        mixed $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value = trim(
            (string) $value
        );

        return $value !== ''
            ? $value
            : null;
    }
}
