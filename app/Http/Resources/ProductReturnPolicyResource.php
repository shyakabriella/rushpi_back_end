<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ProductReturnPolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * @mixin ProductReturnPolicy
 */
final class ProductReturnPolicyResource extends JsonResource
{
    /**
     * Transform the return policy into an API response.
     *
     * @return array<string, mixed>
     */
    public function toArray(
        Request $request
    ): array {
        return [
            /*
            |--------------------------------------------------------------------------
            | Identity
            |--------------------------------------------------------------------------
            */

            'public_id' =>
                (string) $this->public_id,

            /*
            |--------------------------------------------------------------------------
            | Product
            |--------------------------------------------------------------------------
            */

            'product' =>
                $this->whenLoaded(
                    'product',
                    function (): ?array {
                        if ($this->product === null) {
                            return null;
                        }

                        return [
                            'public_id' =>
                                (string) $this
                                    ->product
                                    ->public_id,

                            'name' =>
                                (string) $this
                                    ->product
                                    ->name,

                            'slug' =>
                                (string) $this
                                    ->product
                                    ->slug,

                            'status' =>
                                method_exists(
                                    $this->product,
                                    'statusValue'
                                )
                                    ? $this
                                        ->product
                                        ->statusValue()
                                    : (
                                        $this->product
                                            ->status
                                            instanceof
                                            \BackedEnum
                                                ? (string) $this
                                                    ->product
                                                    ->status
                                                    ->value
                                                : (string) $this
                                                    ->product
                                                    ->status
                                    ),
                        ];
                    }
                ),

            /*
            |--------------------------------------------------------------------------
            | Return eligibility
            |--------------------------------------------------------------------------
            */

            'is_returnable' =>
                (bool) $this->is_returnable,

            'return_window_days' =>
                $this->is_returnable
                    ? (
                        $this->return_window_days
                            !== null
                                ? (int) $this
                                    ->return_window_days
                                : null
                    )
                    : null,

            /*
            |--------------------------------------------------------------------------
            | Available resolutions
            |--------------------------------------------------------------------------
            */

            'resolutions' => [
                'allow_refund' =>
                    (bool) $this->allow_refund,

                'allow_exchange' =>
                    (bool) $this->allow_exchange,
            ],

            /*
            |--------------------------------------------------------------------------
            | Return requirements
            |--------------------------------------------------------------------------
            */

            'requirements' => [
                'requires_original_packaging' =>
                    (bool) $this
                        ->requires_original_packaging,

                'requires_proof_of_purchase' =>
                    (bool) $this
                        ->requires_proof_of_purchase,
            ],

            /*
            |--------------------------------------------------------------------------
            | Restocking fee
            |--------------------------------------------------------------------------
            */

            'restocking_fee' => [
                'percent' =>
                    $this
                        ->restockingFeePercent(),

                'applies' =>
                    $this
                        ->hasRestockingFee(),
            ],

            /*
            |--------------------------------------------------------------------------
            | Return-shipping responsibility
            |--------------------------------------------------------------------------
            */

            'return_shipping' => [
                'payer' =>
                    (string) $this
                        ->return_shipping_payer,

                'label' =>
                    $this
                        ->shippingPayerLabel(),
            ],

            /*
            |--------------------------------------------------------------------------
            | Accepted conditions
            |--------------------------------------------------------------------------
            */

            'accepted_conditions' =>
                collect(
                    $this
                        ->acceptedConditions()
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

            /*
            |--------------------------------------------------------------------------
            | Refund methods
            |--------------------------------------------------------------------------
            */

            'refund_methods' =>
                collect(
                    $this
                        ->refundMethods()
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

            /*
            |--------------------------------------------------------------------------
            | Customer-facing information
            |--------------------------------------------------------------------------
            */

            'instructions' =>
                $this->instructions,

            'non_returnable_reason' =>
                !$this->is_returnable
                    ? $this
                        ->non_returnable_reason
                    : null,

            /*
            |--------------------------------------------------------------------------
            | Availability
            |--------------------------------------------------------------------------
            */

            'is_active' =>
                (bool) $this->is_active,

            'allows_returns' =>
                $this->allowsReturns(),

            'allows_refunds' =>
                $this->allowsRefunds(),

            'allows_exchanges' =>
                $this->allowsExchanges(),

            /*
            |--------------------------------------------------------------------------
            | Configuration readiness
            |--------------------------------------------------------------------------
            */

            'configuration' => [
                'is_valid' =>
                    $this
                        ->hasValidConfiguration(),

                'errors' =>
                    $this
                        ->configurationErrors(),
            ],

            /*
            |--------------------------------------------------------------------------
            | Customer-safe representation
            |--------------------------------------------------------------------------
            */

            'customer_policy' =>
                $this
                    ->toCustomerPolicy(),

            /*
            |--------------------------------------------------------------------------
            | Audit information
            |--------------------------------------------------------------------------
            */

            'audit' => [
                'created_by' =>
                    $this->whenLoaded(
                        'createdBy',
                        function (): ?array {
                            if (
                                $this->createdBy
                                === null
                            ) {
                                return null;
                            }

                            return [
                                'public_id' =>
                                    (string) (
                                        $this
                                            ->createdBy
                                            ->public_id
                                        ?? ''
                                    ),

                                'name' =>
                                    (string) (
                                        $this
                                            ->createdBy
                                            ->name
                                        ?? ''
                                    ),
                            ];
                        }
                    ),

                'updated_by' =>
                    $this->whenLoaded(
                        'updatedBy',
                        function (): ?array {
                            if (
                                $this->updatedBy
                                === null
                            ) {
                                return null;
                            }

                            return [
                                'public_id' =>
                                    (string) (
                                        $this
                                            ->updatedBy
                                            ->public_id
                                        ?? ''
                                    ),

                                'name' =>
                                    (string) (
                                        $this
                                            ->updatedBy
                                            ->name
                                        ?? ''
                                    ),
                            ];
                        }
                    ),
            ],

            /*
            |--------------------------------------------------------------------------
            | Timestamps
            |--------------------------------------------------------------------------
            */

            'created_at' =>
                $this->created_at
                    ?->toISOString(),

            'updated_at' =>
                $this->updated_at
                    ?->toISOString(),
        ];
    }

    /**
     * Add form configuration that helps seller applications construct
     * return-policy forms without hardcoding available values.
     *
     * @param Request $request
     *
     * @return array<string, mixed>
     */
    public function with(
        Request $request
    ): array {
        return [
            'options' => [
                'shipping_payers' =>
                    collect(
                        ProductReturnPolicy
                            ::shippingPayers()
                    )
                        ->map(
                            static fn (
                                string $payer
                            ): array => [
                                'value' =>
                                    $payer,

                                'label' =>
                                    Str::headline(
                                        $payer
                                    ),
                            ]
                        )
                        ->values()
                        ->all(),

                'accepted_conditions' =>
                    collect(
                        ProductReturnPolicy
                            ::commonConditions()
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
                        ProductReturnPolicy
                            ::supportedRefundMethods()
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
            ],
        ];
    }
}
