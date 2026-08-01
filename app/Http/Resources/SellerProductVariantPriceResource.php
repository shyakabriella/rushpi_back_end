<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\ProductVariantPrice
 */
class SellerProductVariantPriceResource extends JsonResource
{
    /**
     * Transform variant pricing into an API response.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'currency' => $this->currency,

            'selling_price' => $this->selling_price,

            'compare_at_price' => $this->compare_at_price,

            /*
             * Cost price is private seller information.
             * It must not be returned by the future public
             * product catalog resource.
             */
            'cost_price' => $this->cost_price,

            'formatted' => [
                'selling_price' =>
                    $this->formattedSellingPrice(),

                'compare_at_price' =>
                    $this->formattedCompareAtPrice(),

                'cost_price' =>
                    $this->formattedCostPrice(),
            ],

            'discount' => [
                'is_discounted' =>
                    $this->isDiscounted(),

                'percentage' =>
                    $this->discountPercentage(),

                'amount' =>
                    $this->discountAmount(),

                'formatted_amount' =>
                    $this->formattedDiscountAmount(),
            ],

            'profit' => [
                'amount' =>
                    $this->profitAmount(),

                'formatted_amount' =>
                    $this->formattedProfitAmount(),

                'margin_percentage' =>
                    $this->profitMarginPercentage(),
            ],

            /*
             * Variant information is returned only when
             * the variant relationship is loaded.
             */
            'variant' => $this->whenLoaded(
                'variant',
                function (): ?array {
                    if ($this->variant === null) {
                        return null;
                    }

                    return [
                        'public_id' =>
                            $this->variant->public_id,

                        'sku' =>
                            $this->variant->sku,

                        'name' =>
                            $this->variant->name,

                        'is_default' =>
                            (bool) $this->variant->is_default,

                        'is_active' =>
                            (bool) $this->variant->is_active,
                    ];
                }
            ),

            /*
             * User information is included only when the
             * controller loads these relationships.
             */
            'created_by' => $this->whenLoaded(
                'createdBy',
                function (): ?array {
                    if ($this->createdBy === null) {
                        return null;
                    }

                    return [
                        'public_id' =>
                            $this->createdBy->public_id,

                        'name' =>
                            $this->createdBy->name,

                        'email' =>
                            $this->createdBy->email,
                    ];
                }
            ),

            'updated_by' => $this->whenLoaded(
                'updatedBy',
                function (): ?array {
                    if ($this->updatedBy === null) {
                        return null;
                    }

                    return [
                        'public_id' =>
                            $this->updatedBy->public_id,

                        'name' =>
                            $this->updatedBy->name,

                        'email' =>
                            $this->updatedBy->email,
                    ];
                }
            ),

            'created_at' =>
                $this->created_at?->toISOString(),

            'updated_at' =>
                $this->updated_at?->toISOString(),
        ];
    }

    /**
     * Return a formatted comparison price.
     */
    private function formattedCompareAtPrice(): ?string
    {
        if ($this->compare_at_price === null) {
            return null;
        }

        return $this->formatMoney(
            $this->compare_at_price
        );
    }

    /**
     * Return a formatted cost price.
     */
    private function formattedCostPrice(): ?string
    {
        if ($this->cost_price === null) {
            return null;
        }

        return $this->formatMoney(
            $this->cost_price
        );
    }

    /**
     * Calculate the discount amount.
     */
    private function discountAmount(): ?string
    {
        if (! $this->isDiscounted()) {
            return null;
        }

        $amount =
            (float) $this->compare_at_price
            - (float) $this->selling_price;

        return number_format(
            max($amount, 0),
            2,
            '.',
            ''
        );
    }

    /**
     * Return a formatted discount amount.
     */
    private function formattedDiscountAmount(): ?string
    {
        $amount = $this->discountAmount();

        if ($amount === null) {
            return null;
        }

        return $this->formatMoney($amount);
    }

    /**
     * Calculate the estimated profit amount.
     */
    private function profitAmount(): ?string
    {
        if ($this->cost_price === null) {
            return null;
        }

        $profit =
            (float) $this->selling_price
            - (float) $this->cost_price;

        return number_format(
            $profit,
            2,
            '.',
            ''
        );
    }

    /**
     * Return a formatted estimated profit amount.
     */
    private function formattedProfitAmount(): ?string
    {
        $profit = $this->profitAmount();

        if ($profit === null) {
            return null;
        }

        return $this->formatMoney($profit);
    }

    /**
     * Calculate the estimated profit margin.
     */
    private function profitMarginPercentage(): ?float
    {
        if (
            $this->cost_price === null
            || (float) $this->selling_price <= 0
        ) {
            return null;
        }

        $profit =
            (float) $this->selling_price
            - (float) $this->cost_price;

        return round(
            ($profit / (float) $this->selling_price) * 100,
            2
        );
    }

    /**
     * Format a monetary value using the stored currency.
     */
    private function formatMoney(
        string|int|float $amount
    ): string {
        return sprintf(
            '%s %s',
            strtoupper((string) $this->currency),
            number_format(
                (float) $amount,
                2,
                '.',
                ','
            )
        );
    }
}
