<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Models\Product;
use App\Models\SellerProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductVariantRequest extends FormRequest
{
    /**
     * Only an active seller owner or manager may add
     * variants to products owned by their approved business.
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
     * Validation rules for creating a product variant.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $productId = $this->product()?->getKey() ?? 0;

        return [
            'sku' => [
                'required',
                'string',
                'min:2',
                'max:150',
                'regex:/^[A-Za-z0-9._-]+$/',

                Rule::unique('product_variants', 'sku')
                    ->where(
                        function ($query) use ($productId): void {
                            $query
                                ->where('product_id', $productId)
                                ->whereNull('deleted_at');
                        }
                    ),
            ],

            'barcode' => [
                'nullable',
                'string',
                'max:150',
                'regex:/^[A-Za-z0-9._-]+$/',
            ],

            'name' => [
                'required',
                'string',
                'min:2',
                'max:255',
            ],

            /*
             * Example:
             *
             * {
             *   "color": "Black Titanium",
             *   "storage": "256 GB",
             *   "ram": "8 GB"
             * }
             */
            'attributes' => [
                'nullable',
                'array',
                'max:50',
            ],

            'attributes.*' => [
                'nullable',
            ],

            'weight_grams' => [
                'nullable',
                'integer',
                'min:0',
                'max:4294967295',
            ],

            'length_cm' => [
                'nullable',
                'numeric',
                'min:0',
                'max:99999999.99',
                'decimal:0,2',
            ],

            'width_cm' => [
                'nullable',
                'numeric',
                'min:0',
                'max:99999999.99',
                'decimal:0,2',
            ],

            'height_cm' => [
                'nullable',
                'numeric',
                'min:0',
                'max:99999999.99',
                'decimal:0,2',
            ],

            'is_default' => [
                'sometimes',
                'boolean',
            ],

            'is_active' => [
                'sometimes',
                'boolean',
            ],

            'sort_order' => [
                'sometimes',
                'integer',
                'min:0',
                'max:4294967295',
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
            'sku.required' =>
                'The product variant SKU is required.',

            'sku.min' =>
                'The SKU must contain at least 2 characters.',

            'sku.max' =>
                'The SKU may not exceed 150 characters.',

            'sku.regex' =>
                'The SKU may contain only letters, numbers, dots, dashes and underscores.',

            'sku.unique' =>
                'This product already has a variant using the same SKU.',

            'barcode.regex' =>
                'The barcode may contain only letters, numbers, dots, dashes and underscores.',

            'barcode.max' =>
                'The barcode may not exceed 150 characters.',

            'name.required' =>
                'The product variant name is required.',

            'name.min' =>
                'The variant name must contain at least 2 characters.',

            'name.max' =>
                'The variant name may not exceed 255 characters.',

            'attributes.array' =>
                'Variant attributes must be provided as an object or array.',

            'weight_grams.integer' =>
                'The variant weight must be provided in whole grams.',

            'weight_grams.min' =>
                'The variant weight cannot be negative.',

            'length_cm.numeric' =>
                'The package length must be a valid number.',

            'width_cm.numeric' =>
                'The package width must be a valid number.',

            'height_cm.numeric' =>
                'The package height must be a valid number.',

            'length_cm.min' =>
                'The package length cannot be negative.',

            'width_cm.min' =>
                'The package width cannot be negative.',

            'height_cm.min' =>
                'The package height cannot be negative.',

            'is_default.boolean' =>
                'The default variant value must be true or false.',

            'is_active.boolean' =>
                'The active variant value must be true or false.',

            'sort_order.min' =>
                'The variant sort order cannot be negative.',
        ];
    }

    /**
     * Normalize request data before validation.
     */
    protected function prepareForValidation(): void
    {
        $normalized = [];

        if ($this->has('sku')) {
            $sku = $this->input('sku');

            $normalized['sku'] = is_string($sku)
                ? strtoupper(trim($sku))
                : $sku;
        }

        if ($this->has('barcode')) {
            $barcode = $this->input('barcode');

            $normalized['barcode'] = is_string($barcode)
                ? trim($barcode)
                : $barcode;

            if ($normalized['barcode'] === '') {
                $normalized['barcode'] = null;
            }
        }

        if ($this->has('name')) {
            $name = $this->input('name');

            $normalized['name'] = is_string($name)
                ? trim($name)
                : $name;
        }

        if ($this->has('is_default')) {
            $normalized['is_default'] =
                $this->boolean('is_default');
        } else {
            $normalized['is_default'] = false;
        }

        if ($this->has('is_active')) {
            $normalized['is_active'] =
                $this->boolean('is_active');
        } else {
            $normalized['is_active'] = true;
        }

        if (! $this->has('sort_order')) {
            $normalized['sort_order'] = 0;
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
