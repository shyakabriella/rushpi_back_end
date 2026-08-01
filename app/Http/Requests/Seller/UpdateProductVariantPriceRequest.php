<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SellerProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateProductVariantPriceRequest extends FormRequest
{
    /**
     * Only an active owner or manager of an approved seller
     * may update pricing for their own product variant.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $sellerProfile = $this->sellerProfile();
        $product = $this->product();
        $variant = $this->variant();

        if (
            $user === null
            || $sellerProfile === null
            || $product === null
            || $variant === null
        ) {
            return false;
        }

        if (! $sellerProfile->isApproved()) {
            return false;
        }

        if (
            (int) $product->seller_profile_id
            !== (int) $sellerProfile->getKey()
        ) {
            return false;
        }

        if (
            (int) $variant->product_id
            !== (int) $product->getKey()
        ) {
            return false;
        }

        if (! $product->canBeEditedBySeller()) {
            return false;
        }

        return $sellerProfile->members()
            ->where('user_id', $user->getKey())
            ->where('status', 'active')
            ->whereIn('role', [
                'owner',
                'manager',
            ])
            ->exists();
    }

    /**
     * Validation rules for updating variant pricing.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'currency' => [
                'sometimes',
                'required',
                'string',
                'size:3',
                'regex:/^[A-Z]{3}$/',
            ],

            'selling_price' => [
                'sometimes',
                'required',
                'numeric',
                'decimal:0,2',
                'min:0.01',
                'max:999999999999.99',
            ],

            'compare_at_price' => [
                'sometimes',
                'nullable',
                'numeric',
                'decimal:0,2',
                'min:0.01',
                'max:999999999999.99',
            ],

            'cost_price' => [
                'sometimes',
                'nullable',
                'numeric',
                'decimal:0,2',
                'min:0',
                'max:999999999999.99',
            ],
        ];
    }

    /**
     * Perform additional pricing business validation.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(
            function (Validator $validator): void {
                $variant = $this->variant();

                if ($variant === null) {
                    return;
                }

                $currentPrice = $variant->price;

                if ($currentPrice === null) {
                    $validator->errors()->add(
                        'selling_price',
                        'This product variant does not have pricing yet. Use the create price endpoint first.'
                    );

                    return;
                }

                /*
                 * The comparison price must remain equal to or
                 * greater than the effective selling price.
                 *
                 * Effective selling price means:
                 * - the new selling_price when supplied, or
                 * - the existing database selling price.
                 */
                if (! $this->has('compare_at_price')) {
                    return;
                }

                $compareAtPrice = $this->input(
                    'compare_at_price'
                );

                if (
                    $compareAtPrice === null
                    || $compareAtPrice === ''
                    || ! is_numeric($compareAtPrice)
                ) {
                    return;
                }

                $sellingPrice = $this->has(
                    'selling_price'
                )
                    ? $this->input('selling_price')
                    : $currentPrice->selling_price;

                if (! is_numeric($sellingPrice)) {
                    return;
                }

                if (
                    (float) $compareAtPrice
                    < (float) $sellingPrice
                ) {
                    $validator->errors()->add(
                        'compare_at_price',
                        'The comparison price must be equal to or greater than the selling price.'
                    );
                }
            }
        );
    }

    /**
     * Custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'currency.required' =>
                'The pricing currency cannot be empty.',

            'currency.size' =>
                'The currency must use a three-letter ISO code such as RWF, USD or EUR.',

            'currency.regex' =>
                'The currency must contain exactly three uppercase letters.',

            'selling_price.required' =>
                'The variant selling price cannot be empty.',

            'selling_price.numeric' =>
                'The selling price must be a valid number.',

            'selling_price.decimal' =>
                'The selling price may contain a maximum of two decimal places.',

            'selling_price.min' =>
                'The selling price must be greater than zero.',

            'selling_price.max' =>
                'The selling price is too large.',

            'compare_at_price.numeric' =>
                'The comparison price must be a valid number.',

            'compare_at_price.decimal' =>
                'The comparison price may contain a maximum of two decimal places.',

            'compare_at_price.min' =>
                'The comparison price must be greater than zero.',

            'compare_at_price.max' =>
                'The comparison price is too large.',

            'cost_price.numeric' =>
                'The cost price must be a valid number.',

            'cost_price.decimal' =>
                'The cost price may contain a maximum of two decimal places.',

            'cost_price.min' =>
                'The cost price cannot be negative.',

            'cost_price.max' =>
                'The cost price is too large.',
        ];
    }

    /**
     * Normalize only price fields supplied by the seller.
     */
    protected function prepareForValidation(): void
    {
        $normalized = [];

        if ($this->has('currency')) {
            $currency = $this->input('currency');

            $normalized['currency'] = is_string($currency)
                ? strtoupper(trim($currency))
                : $currency;
        }

        foreach ([
            'selling_price',
            'compare_at_price',
            'cost_price',
        ] as $field) {
            if (! $this->has($field)) {
                continue;
            }

            $value = $this->input($field);

            if (is_string($value)) {
                $value = trim($value);
            }

            /*
             * Optional comparison and cost prices may be cleared
             * by sending an empty string or null.
             */
            if (
                in_array(
                    $field,
                    [
                        'compare_at_price',
                        'cost_price',
                    ],
                    true
                )
                && $value === ''
            ) {
                $value = null;
            }

            $normalized[$field] = $value;
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    /**
     * Resolve the seller profile from route model binding.
     */
    private function sellerProfile(): ?SellerProfile
    {
        $sellerProfile = $this->route(
            'sellerProfile'
        );

        if ($sellerProfile instanceof SellerProfile) {
            return $sellerProfile;
        }

        if (
            is_string($sellerProfile)
            && $sellerProfile !== ''
        ) {
            return SellerProfile::query()
                ->where(
                    'public_id',
                    $sellerProfile
                )
                ->first();
        }

        return null;
    }

    /**
     * Resolve the product from route model binding.
     */
    private function product(): ?Product
    {
        $product = $this->route('product');

        if ($product instanceof Product) {
            return $product;
        }

        if (
            is_string($product)
            && $product !== ''
        ) {
            return Product::query()
                ->where(
                    'public_id',
                    $product
                )
                ->first();
        }

        return null;
    }

    /**
     * Resolve the variant from route model binding.
     */
    private function variant(): ?ProductVariant
    {
        $variant = $this->route('variant');

        if ($variant instanceof ProductVariant) {
            return $variant;
        }

        if (
            is_string($variant)
            && $variant !== ''
        ) {
            return ProductVariant::query()
                ->where(
                    'public_id',
                    $variant
                )
                ->first();
        }

        return null;
    }
}
