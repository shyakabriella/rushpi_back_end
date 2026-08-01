<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Enums\ProductCondition;
use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\SellerProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    /**
     * Only an active seller owner or manager may update
     * products belonging to their approved seller profile.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        $sellerProfile = $this->sellerProfile();
        $product = $this->product();

        if (
            $user === null
            || $sellerProfile === null
            || $product === null
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
     * Validation rules for updating a seller product.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $sellerProfile = $this->sellerProfile();
        $product = $this->product();

        return [
            'category_public_id' => [
                'sometimes',
                'required',
                'string',
                Rule::exists('categories', 'public_id')
                    ->where(
                        function ($query): void {
                            $query
                                ->where('is_active', true)
                                ->whereNull('deleted_at');
                        }
                    ),
            ],

            'brand_public_id' => [
                'sometimes',
                'nullable',
                'string',
                Rule::exists('brands', 'public_id')
                    ->where(
                        function ($query): void {
                            $query
                                ->where('is_active', true)
                                ->whereNull('deleted_at');
                        }
                    ),
            ],

            'name' => [
                'sometimes',
                'required',
                'string',
                'min:2',
                'max:255',
            ],

            'slug' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                'alpha_dash',

                Rule::unique('products', 'slug')
                    ->ignore($product?->getKey())
                    ->where(
                        function ($query) use (
                            $sellerProfile
                        ): void {
                            $query
                                ->where(
                                    'seller_profile_id',
                                    $sellerProfile?->getKey() ?? 0
                                )
                                ->whereNull('deleted_at');
                        }
                    ),
            ],

            'short_description' => [
                'sometimes',
                'nullable',
                'string',
                'max:500',
            ],

            'description' => [
                'sometimes',
                'nullable',
                'string',
                'max:50000',
            ],

            'condition' => [
                'sometimes',
                'required',
                Rule::enum(ProductCondition::class),
            ],

            'warranty_months' => [
                'sometimes',
                'nullable',
                'integer',
                'min:0',
                'max:120',
            ],

            'specifications' => [
                'sometimes',
                'nullable',
                'array',
                'max:100',
            ],

            'specifications.*' => [
                'nullable',
            ],
        ];
    }

    /**
     * Custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'category_public_id.required' =>
                'A product category is required.',

            'category_public_id.exists' =>
                'The selected category does not exist or is inactive.',

            'brand_public_id.exists' =>
                'The selected brand does not exist or is inactive.',

            'name.required' =>
                'The product name cannot be empty.',

            'name.min' =>
                'The product name must contain at least 2 characters.',

            'name.max' =>
                'The product name may not exceed 255 characters.',

            'slug.alpha_dash' =>
                'The product slug may contain only letters, numbers, dashes and underscores.',

            'slug.unique' =>
                'Your seller business already has another product using this slug.',

            'short_description.max' =>
                'The short description may not exceed 500 characters.',

            'description.max' =>
                'The product description may not exceed 50,000 characters.',

            'condition.required' =>
                'The product condition cannot be empty.',

            'condition.enum' =>
                'The selected product condition is invalid.',

            'warranty_months.min' =>
                'Warranty months cannot be negative.',

            'warranty_months.max' =>
                'Warranty months may not exceed 120 months.',

            'specifications.array' =>
                'Product specifications must be provided as an object or array.',
        ];
    }

    /**
     * Normalize only fields included in the request.
     */
    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach ([
            'category_public_id',
            'brand_public_id',
            'name',
            'slug',
            'short_description',
            'description',
            'condition',
        ] as $field) {
            if (! $this->has($field)) {
                continue;
            }

            $value = $this->input($field);

            if (is_string($value)) {
                $value = trim($value);
            }

            $normalized[$field] = $value;
        }

        if (
            array_key_exists('brand_public_id', $normalized)
            && $normalized['brand_public_id'] === ''
        ) {
            $normalized['brand_public_id'] = null;
        }

        if (
            array_key_exists('slug', $normalized)
            && $normalized['slug'] === ''
        ) {
            $normalized['slug'] = null;
        }

        if (
            array_key_exists('condition', $normalized)
            && is_string($normalized['condition'])
        ) {
            $normalized['condition'] = strtolower(
                $normalized['condition']
            );
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
        $sellerProfile = $this->route('sellerProfile');

        if ($sellerProfile instanceof SellerProfile) {
            return $sellerProfile;
        }

        if (
            is_string($sellerProfile)
            && $sellerProfile !== ''
        ) {
            return SellerProfile::query()
                ->where('public_id', $sellerProfile)
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
                ->where('public_id', $product)
                ->first();
        }

        return null;
    }
}
