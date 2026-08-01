<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SellerProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Validator;

class SubmitProductForReviewRequest extends FormRequest
{
    /**
     * Only an active seller owner or manager may submit
     * their product for administrator moderation.
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
     * Product submission does not require request-body fields.
     *
     * Product readiness is checked using database records.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Check whether the product is ready for moderation.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(
            function (Validator $validator): void {
                $sellerProfile = $this->sellerProfile();
                $product = $this->product();

                if (
                    $sellerProfile === null
                    || $product === null
                ) {
                    return;
                }

                $this->validateProductStatus(
                    $validator,
                    $product
                );

                $this->validateCategory(
                    $validator,
                    $product
                );

                $this->validateBrand(
                    $validator,
                    $product
                );

                $activeVariants = $this->activeVariants(
                    $product
                );

                $this->validateVariants(
                    $validator,
                    $activeVariants
                );

                $this->validateProductMedia(
                    $validator,
                    $product
                );
            }
        );
    }

    /**
     * Ensure the product is in a submittable status.
     */
    private function validateProductStatus(
        Validator $validator,
        Product $product
    ): void {
        $status = $this->resolveProductStatus(
            $product
        );

        if (
            ! in_array(
                $status,
                [
                    ProductStatus::DRAFT,
                    ProductStatus::REJECTED,
                ],
                true
            )
        ) {
            $validator->errors()->add(
                'product',
                match ($status) {
                    ProductStatus::PENDING_REVIEW =>
                        'This product has already been submitted and is waiting for moderation.',

                    ProductStatus::APPROVED =>
                        'This product has already been approved.',

                    ProductStatus::SUSPENDED =>
                        'A suspended product cannot be submitted for moderation.',

                    ProductStatus::ARCHIVED =>
                        'An archived product cannot be submitted for moderation.',

                    default =>
                        'This product cannot currently be submitted for moderation.',
                }
            );
        }
    }

    /**
     * Ensure the selected product category is still active.
     */
    private function validateCategory(
        Validator $validator,
        Product $product
    ): void {
        $categoryExists = $product->category()
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->exists();

        if (! $categoryExists) {
            $validator->errors()->add(
                'category',
                'The product must belong to an active category before submission.'
            );
        }
    }

    /**
     * Ensure the selected brand remains active.
     */
    private function validateBrand(
        Validator $validator,
        Product $product
    ): void {
        if ($product->brand_id === null) {
            return;
        }

        $brandExists = $product->brand()
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->exists();

        if (! $brandExists) {
            $validator->errors()->add(
                'brand',
                'The selected product brand is inactive or unavailable.'
            );
        }
    }

    /**
     * Load active variants and their required relationships.
     *
     * @return Collection<int, ProductVariant>
     */
    private function activeVariants(
        Product $product
    ): Collection {
        return $product->variants()
            ->where('is_active', true)
            ->with([
                'price',
                'inventoryStock',
            ])
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * Validate active product variants.
     *
     * @param Collection<int, ProductVariant> $variants
     */
    private function validateVariants(
        Validator $validator,
        Collection $variants
    ): void {
        if ($variants->isEmpty()) {
            $validator->errors()->add(
                'variants',
                'Add at least one active product variant before submission.'
            );

            return;
        }

        $hasDefaultVariant = $variants->contains(
            fn (ProductVariant $variant): bool =>
                (bool) $variant->is_default
        );

        if (! $hasDefaultVariant) {
            $validator->errors()->add(
                'variants',
                'One active product variant must be marked as the default variant.'
            );
        }

        foreach ($variants as $variant) {
            $variantName = $this->variantIdentifier(
                $variant
            );

            if ($variant->price === null) {
                $validator->errors()->add(
                    'variants',
                    sprintf(
                        'Variant %s does not have pricing.',
                        $variantName
                    )
                );
            } elseif (
                (float) $variant->price->selling_price <= 0
            ) {
                $validator->errors()->add(
                    'variants',
                    sprintf(
                        'Variant %s must have a selling price greater than zero.',
                        $variantName
                    )
                );
            }

            if ($variant->inventoryStock === null) {
                $validator->errors()->add(
                    'variants',
                    sprintf(
                        'Inventory has not been initialized for variant %s.',
                        $variantName
                    )
                );
            }
        }
    }

    /**
     * Ensure the product has valid public media.
     */
    private function validateProductMedia(
        Validator $validator,
        Product $product
    ): void {
        $mediaQuery = $product->media();

        if (! $mediaQuery->exists()) {
            $validator->errors()->add(
                'media',
                'Upload at least one product image before submission.'
            );

            return;
        }

        $hasPrimaryMedia = $product->media()
            ->where('is_primary', true)
            ->exists();

        if (! $hasPrimaryMedia) {
            $validator->errors()->add(
                'media',
                'One product image must be marked as the primary image.'
            );
        }
    }

    /**
     * Return a readable variant identifier.
     */
    private function variantIdentifier(
        ProductVariant $variant
    ): string {
        $sku = trim((string) $variant->sku);

        if ($sku !== '') {
            return sprintf(
                '"%s"',
                $sku
            );
        }

        $name = trim((string) $variant->name);

        if ($name !== '') {
            return sprintf(
                '"%s"',
                $name
            );
        }

        return sprintf(
            '"%s"',
            $variant->public_id
        );
    }

    /**
     * Resolve the product status enum safely.
     */
    private function resolveProductStatus(
        Product $product
    ): ?ProductStatus {
        if ($product->status instanceof ProductStatus) {
            return $product->status;
        }

        if (is_string($product->status)) {
            return ProductStatus::tryFrom(
                $product->status
            );
        }

        return null;
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
}
