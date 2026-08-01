<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Models\Product;
use App\Models\SellerProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProductMediaRequest extends FormRequest
{
    /**
     * Only an active seller owner or manager may upload
     * images for products owned by their approved business.
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
     * Validation rules for uploading product media.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $productId = $this->product()?->getKey() ?? 0;

        return [
            'image' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],

            /*
             * Optional variant association.
             *
             * When omitted, the image belongs to the
             * general product.
             */
            'product_variant_public_id' => [
                'nullable',
                'string',

                Rule::exists(
                    'product_variants',
                    'public_id'
                )->where(
                    function ($query) use ($productId): void {
                        $query
                            ->where('product_id', $productId)
                            ->whereNull('deleted_at');
                    }
                ),
            ],

            'alt_text' => [
                'nullable',
                'string',
                'max:255',
            ],

            'sort_order' => [
                'sometimes',
                'integer',
                'min:0',
                'max:4294967295',
            ],

            'is_primary' => [
                'sometimes',
                'boolean',
            ],
        ];
    }

    /**
     * Perform additional product media validation.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(
            function (Validator $validator): void {
                $product = $this->product();

                if ($product === null) {
                    return;
                }

                /*
                 * Day 3 rule: one product may contain
                 * a maximum of ten active media files.
                 */
                if ($product->media()->count() >= 10) {
                    $validator->errors()->add(
                        'image',
                        'A product may contain a maximum of 10 images.'
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
            'image.required' =>
                'A product image is required.',

            'image.file' =>
                'The uploaded product image is invalid.',

            'image.image' =>
                'The uploaded file must be a valid image.',

            'image.mimes' =>
                'The product image must be a JPG, JPEG, PNG or WebP file.',

            'image.max' =>
                'The product image may not exceed 5 MB.',

            'product_variant_public_id.exists' =>
                'The selected product variant does not belong to this product.',

            'alt_text.max' =>
                'The image alternative text may not exceed 255 characters.',

            'sort_order.min' =>
                'The image sort order cannot be negative.',

            'is_primary.boolean' =>
                'The primary image value must be true or false.',
        ];
    }

    /**
     * Normalize request values before validation.
     */
    protected function prepareForValidation(): void
    {
        $normalized = [];

        if ($this->has('product_variant_public_id')) {
            $variantPublicId = $this->input(
                'product_variant_public_id'
            );

            $normalized['product_variant_public_id'] =
                is_string($variantPublicId)
                    ? trim($variantPublicId)
                    : $variantPublicId;

            if (
                $normalized['product_variant_public_id']
                === ''
            ) {
                $normalized['product_variant_public_id'] =
                    null;
            }
        }

        if ($this->has('alt_text')) {
            $altText = $this->input('alt_text');

            $normalized['alt_text'] =
                is_string($altText)
                    ? trim($altText)
                    : $altText;

            if ($normalized['alt_text'] === '') {
                $normalized['alt_text'] = null;
            }
        }

        if ($this->has('is_primary')) {
            $normalized['is_primary'] =
                $this->boolean('is_primary');
        } else {
            $normalized['is_primary'] = false;
        }

        if (! $this->has('sort_order')) {
            $normalized['sort_order'] = 0;
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    /**
     * Resolve the seller profile from route binding.
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
     * Resolve the product from route binding.
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
