<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Models\Product;
use App\Models\ProductReturnPolicy;
use App\Models\SellerProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpsertProductReturnPolicyRequest extends FormRequest
{
    /**
     * Product resolved from the route.
     */
    private ?Product $resolvedProduct = null;

    /**
     * Seller profile resolved from the route.
     */
    private ?SellerProfile $resolvedSellerProfile = null;

    /**
     * Normalized policy data returned to the controller.
     *
     * @var array<string, mixed>|null
     */
    private ?array $normalizedPolicyData = null;

    /**
     * Authorization is handled by:
     *
     * - auth:sanctum
     * - seller.approved
     * - product ownership verification in the controller
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize submitted values before validation.
     */
    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (
            [
                'is_returnable',
                'allow_refund',
                'allow_exchange',
                'requires_original_packaging',
                'requires_proof_of_purchase',
                'is_active',
            ] as $booleanField
        ) {
            if (!$this->exists($booleanField)) {
                continue;
            }

            $normalized[$booleanField] =
                $this->normalizeBooleanValue(
                    $this->input($booleanField)
                );
        }

        if ($this->exists('return_window_days')) {
            $value = $this->input(
                'return_window_days'
            );

            $normalized['return_window_days'] =
                $this->normalizeNullableNumber(
                    $value
                );
        }

        if (
            $this->exists(
                'restocking_fee_percent'
            )
        ) {
            $value = $this->input(
                'restocking_fee_percent'
            );

            $normalized[
                'restocking_fee_percent'
            ] = $this->normalizeNullableNumber(
                $value
            );
        }

        if (
            $this->exists(
                'return_shipping_payer'
            )
        ) {
            $normalized[
                'return_shipping_payer'
            ] = Str::snake(
                trim(
                    (string) $this->input(
                        'return_shipping_payer',
                        ''
                    )
                )
            );
        }

        if (
            $this->exists(
                'accepted_conditions'
            )
        ) {
            $normalized[
                'accepted_conditions'
            ] = $this->normalizeStringList(
                $this->input(
                    'accepted_conditions'
                )
            );
        }

        if ($this->exists('refund_methods')) {
            $normalized['refund_methods'] =
                $this->normalizeStringList(
                    $this->input(
                        'refund_methods'
                    )
                );
        }

        if ($this->exists('instructions')) {
            $instructions = trim(
                (string) $this->input(
                    'instructions',
                    ''
                )
            );

            $normalized['instructions'] =
                $instructions !== ''
                    ? $instructions
                    : null;
        }

        if (
            $this->exists(
                'non_returnable_reason'
            )
        ) {
            $reason = trim(
                (string) $this->input(
                    'non_returnable_reason',
                    ''
                )
            );

            $normalized[
                'non_returnable_reason'
            ] = $reason !== ''
                ? $reason
                : null;
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    /**
     * Validate product return-policy configuration.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /*
            |--------------------------------------------------------------------------
            | Return eligibility
            |--------------------------------------------------------------------------
            */

            'is_returnable' => [
                'required',
                'boolean',
            ],

            'return_window_days' => [
                Rule::requiredIf(
                    fn (): bool =>
                        $this->input(
                            'is_returnable'
                        ) === true
                ),
                'nullable',
                'integer',
                'min:1',
                'max:365',
            ],

            /*
            |--------------------------------------------------------------------------
            | Available resolutions
            |--------------------------------------------------------------------------
            */

            'allow_refund' => [
                'required',
                'boolean',
            ],

            'allow_exchange' => [
                'required',
                'boolean',
            ],

            /*
            |--------------------------------------------------------------------------
            | Return requirements
            |--------------------------------------------------------------------------
            */

            'requires_original_packaging' => [
                'required',
                'boolean',
            ],

            'requires_proof_of_purchase' => [
                'required',
                'boolean',
            ],

            /*
            |--------------------------------------------------------------------------
            | Restocking fee
            |--------------------------------------------------------------------------
            */

            'restocking_fee_percent' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],

            /*
            |--------------------------------------------------------------------------
            | Return-shipping responsibility
            |--------------------------------------------------------------------------
            */

            'return_shipping_payer' => [
                'required',
                'string',

                Rule::in(
                    ProductReturnPolicy::shippingPayers()
                ),
            ],

            /*
            |--------------------------------------------------------------------------
            | Accepted return conditions
            |--------------------------------------------------------------------------
            */

            'accepted_conditions' => [
                'nullable',
                'array',
                'max:20',
            ],

            'accepted_conditions.*' => [
                'required',
                'string',
                'distinct',

                Rule::in(
                    ProductReturnPolicy::commonConditions()
                ),
            ],

            /*
            |--------------------------------------------------------------------------
            | Refund methods
            |--------------------------------------------------------------------------
            */

            'refund_methods' => [
                'nullable',
                'array',
                'max:10',
            ],

            'refund_methods.*' => [
                'required',
                'string',
                'distinct',

                Rule::in(
                    ProductReturnPolicy::supportedRefundMethods()
                ),
            ],

            /*
            |--------------------------------------------------------------------------
            | Customer-facing information
            |--------------------------------------------------------------------------
            */

            'instructions' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'non_returnable_reason' => [
                Rule::requiredIf(
                    fn (): bool =>
                        $this->input(
                            'is_returnable'
                        ) === false
                ),
                'nullable',
                'string',
                'max:2000',
            ],

            /*
            |--------------------------------------------------------------------------
            | Availability
            |--------------------------------------------------------------------------
            */

            'is_active' => [
                'required',
                'boolean',
            ],
        ];
    }

    /**
     * Validate dependencies between return-policy fields.
     */
    public function withValidator(
        Validator $validator
    ): void {
        $validator->after(
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $isReturnable =
                    $this->input(
                        'is_returnable'
                    ) === true;

                $allowsRefund =
                    $this->input(
                        'allow_refund'
                    ) === true;

                $allowsExchange =
                    $this->input(
                        'allow_exchange'
                    ) === true;

                $refundMethods =
                    $this->input(
                        'refund_methods',
                        []
                    );

                /*
                 * A returnable product must provide at least one customer
                 * resolution.
                 */

                if (
                    $isReturnable
                    && !$allowsRefund
                    && !$allowsExchange
                ) {
                    $validator->errors()->add(
                        'allow_refund',
                        'A returnable product must allow a refund, an exchange, or both.'
                    );

                    $validator->errors()->add(
                        'allow_exchange',
                        'A returnable product must allow a refund, an exchange, or both.'
                    );
                }

                /*
                 * Refund methods are required when refunds are enabled.
                 */

                if (
                    $isReturnable
                    && $allowsRefund
                    && (
                        !is_array($refundMethods)
                        || $refundMethods === []
                    )
                ) {
                    $validator->errors()->add(
                        'refund_methods',
                        'Select at least one refund method when refunds are allowed.'
                    );
                }

                /*
                 * A non-returnable product must clearly explain why returns
                 * are unavailable.
                 */

                if (!$isReturnable) {
                    $reason = trim(
                        (string) $this->input(
                            'non_returnable_reason',
                            ''
                        )
                    );

                    if ($reason === '') {
                        $validator->errors()->add(
                            'non_returnable_reason',
                            'Explain why this product is not returnable.'
                        );
                    }
                }
            }
        );
    }

    /**
     * Build the final normalized policy configuration after validation.
     */
    protected function passedValidation(): void
    {
        $isReturnable =
            $this->input(
                'is_returnable'
            ) === true;

        $allowsRefund =
            $isReturnable
            && $this->input(
                'allow_refund'
            ) === true;

        $allowsExchange =
            $isReturnable
            && $this->input(
                'allow_exchange'
            ) === true;

        $this->normalizedPolicyData = [
            'is_returnable' =>
                $isReturnable,

            'return_window_days' =>
                $isReturnable
                    ? (int) $this->input(
                        'return_window_days',
                        7
                    )
                    : null,

            'allow_refund' =>
                $allowsRefund,

            'allow_exchange' =>
                $allowsExchange,

            'requires_original_packaging' =>
                $isReturnable
                && $this->input(
                    'requires_original_packaging'
                ) === true,

            'requires_proof_of_purchase' =>
                $isReturnable
                && $this->input(
                    'requires_proof_of_purchase'
                ) === true,

            'restocking_fee_percent' =>
                $isReturnable
                    ? round(
                        (float) $this->input(
                            'restocking_fee_percent',
                            0
                        ),
                        2
                    )
                    : 0,

            'return_shipping_payer' =>
                (string) $this->input(
                    'return_shipping_payer',
                    ProductReturnPolicy::SHIPPING_PAYER_CUSTOMER
                ),

            'accepted_conditions' =>
                $isReturnable
                    ? $this->normalizeStringList(
                        $this->input(
                            'accepted_conditions'
                        )
                    )
                    : null,

            'refund_methods' =>
                $allowsRefund
                    ? $this->normalizeStringList(
                        $this->input(
                            'refund_methods'
                        )
                    )
                    : null,

            'instructions' =>
                $this->nullableTrim(
                    $this->input(
                        'instructions'
                    )
                ),

            'non_returnable_reason' =>
                !$isReturnable
                    ? $this->nullableTrim(
                        $this->input(
                            'non_returnable_reason'
                        )
                    )
                    : null,

            'is_active' =>
                $this->input(
                    'is_active'
                ) === true,
        ];

        $this->merge(
            $this->normalizedPolicyData
        );
    }

    /**
     * Return validated and normalized policy data.
     *
     * @param string|null $key
     * @param mixed $default
     *
     * @return mixed
     */
    public function validated(
        $key = null,
        $default = null
    ) {
        $validated =
            $this->normalizedPolicyData
            ?? parent::validated();

        if ($key === null) {
            return $validated;
        }

        return data_get(
            $validated,
            $key,
            $default
        );
    }

    /**
     * Return the complete normalized policy payload.
     *
     * @return array<string, mixed>
     */
    public function policyData(): array
    {
        return $this->normalizedPolicyData
            ?? [];
    }

    /**
     * Resolve the product from route-model binding.
     */
    public function product(): ?Product
    {
        if ($this->resolvedProduct instanceof Product) {
            return $this->resolvedProduct;
        }

        $routeValue = $this->route('product');

        if ($routeValue instanceof Product) {
            $this->resolvedProduct =
                $routeValue;

            return $this->resolvedProduct;
        }

        if (
            is_int($routeValue)
            || (
                is_string($routeValue)
                && ctype_digit($routeValue)
            )
        ) {
            $this->resolvedProduct =
                Product::query()->find(
                    (int) $routeValue
                );

            return $this->resolvedProduct;
        }

        if (
            is_string($routeValue)
            && trim($routeValue) !== ''
        ) {
            $this->resolvedProduct =
                Product::query()
                    ->where(
                        'public_id',
                        trim($routeValue)
                    )
                    ->first();

            return $this->resolvedProduct;
        }

        return null;
    }

    /**
     * Resolve the seller profile from route-model binding.
     */
    public function sellerProfile(): ?SellerProfile
    {
        if (
            $this->resolvedSellerProfile
            instanceof SellerProfile
        ) {
            return $this
                ->resolvedSellerProfile;
        }

        $routeValue =
            $this->route('sellerProfile');

        if (
            $routeValue instanceof
            SellerProfile
        ) {
            $this->resolvedSellerProfile =
                $routeValue;

            return $this
                ->resolvedSellerProfile;
        }

        if (
            is_int($routeValue)
            || (
                is_string($routeValue)
                && ctype_digit($routeValue)
            )
        ) {
            $this->resolvedSellerProfile =
                SellerProfile::query()->find(
                    (int) $routeValue
                );

            return $this
                ->resolvedSellerProfile;
        }

        if (
            is_string($routeValue)
            && trim($routeValue) !== ''
        ) {
            $this->resolvedSellerProfile =
                SellerProfile::query()
                    ->where(
                        'public_id',
                        trim($routeValue)
                    )
                    ->first();

            return $this
                ->resolvedSellerProfile;
        }

        return null;
    }

    /**
     * Determine whether the route product belongs to the route seller.
     */
    public function productBelongsToSeller(): bool
    {
        $product = $this->product();

        $sellerProfile =
            $this->sellerProfile();

        if (
            !$product instanceof Product
            || !$sellerProfile
                instanceof SellerProfile
        ) {
            return false;
        }

        return (int) $product
            ->seller_profile_id
            === (int) $sellerProfile
                ->getKey();
    }

    /**
     * Custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'is_returnable.required' =>
                'Specify whether this product is returnable.',

            'return_window_days.required' =>
                'A return window is required for a returnable product.',

            'return_window_days.min' =>
                'The return window must be at least one day.',

            'return_window_days.max' =>
                'The return window cannot exceed 365 days.',

            'allow_refund.required' =>
                'Specify whether refunds are allowed.',

            'allow_exchange.required' =>
                'Specify whether exchanges are allowed.',

            'requires_original_packaging.required' =>
                'Specify whether original packaging is required.',

            'requires_proof_of_purchase.required' =>
                'Specify whether proof of purchase is required.',

            'restocking_fee_percent.min' =>
                'The restocking fee cannot be negative.',

            'restocking_fee_percent.max' =>
                'The restocking fee cannot exceed 100 percent.',

            'return_shipping_payer.required' =>
                'Select who pays for return shipping.',

            'return_shipping_payer.in' =>
                'The selected return-shipping payer is invalid.',

            'accepted_conditions.array' =>
                'Accepted return conditions must be submitted as an array.',

            'accepted_conditions.*.distinct' =>
                'Accepted return conditions must not contain duplicates.',

            'accepted_conditions.*.in' =>
                'One or more accepted return conditions are invalid.',

            'refund_methods.array' =>
                'Refund methods must be submitted as an array.',

            'refund_methods.*.distinct' =>
                'Refund methods must not contain duplicates.',

            'refund_methods.*.in' =>
                'One or more refund methods are invalid.',

            'non_returnable_reason.required' =>
                'Explain why this product is not returnable.',

            'is_active.required' =>
                'Specify whether the return policy is active.',
        ];
    }

    /**
     * Human-readable validation attribute names.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'is_returnable' =>
                'return eligibility',

            'return_window_days' =>
                'return window',

            'allow_refund' =>
                'refund availability',

            'allow_exchange' =>
                'exchange availability',

            'requires_original_packaging' =>
                'original packaging requirement',

            'requires_proof_of_purchase' =>
                'proof of purchase requirement',

            'restocking_fee_percent' =>
                'restocking fee',

            'return_shipping_payer' =>
                'return shipping payer',

            'accepted_conditions' =>
                'accepted return conditions',

            'refund_methods' =>
                'refund methods',

            'non_returnable_reason' =>
                'non-returnable reason',

            'is_active' =>
                'policy availability',
        ];
    }

    /**
     * Normalize common boolean representations.
     */
    private function normalizeBooleanValue(
        mixed $value
    ): mixed {
        if (is_bool($value)) {
            return $value;
        }

        if (
            $value === 1
            || $value === '1'
        ) {
            return true;
        }

        if (
            $value === 0
            || $value === '0'
        ) {
            return false;
        }

        if (is_string($value)) {
            return match (
                strtolower(trim($value))
            ) {
                'true',
                'yes',
                'on' =>
                    true,

                'false',
                'no',
                'off' =>
                    false,

                default =>
                    $value,
            };
        }

        return $value;
    }

    /**
     * Normalize nullable numeric input.
     */
    private function normalizeNullableNumber(
        mixed $value
    ): mixed {
        if ($value === null) {
            return null;
        }

        if (
            is_string($value)
            && trim($value) === ''
        ) {
            return null;
        }

        return is_string($value)
            ? trim($value)
            : $value;
    }

    /**
     * Normalize a list of string values.
     *
     * @return array<int, string>|null
     */
    private function normalizeStringList(
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
     * Trim nullable text.
     */
    private function nullableTrim(
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
