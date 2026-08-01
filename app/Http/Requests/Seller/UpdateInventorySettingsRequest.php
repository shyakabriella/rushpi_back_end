<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SellerProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateInventorySettingsRequest extends FormRequest
{
    /**
     * Only an active owner or manager of an approved seller
     * may update inventory settings for their own variant.
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

        /*
         * Inventory settings are operational settings.
         *
         * They may be updated while a product is approved
         * without returning the product to draft.
         */
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
     * Validation rules for inventory settings.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /*
             * When available stock reaches or falls below this
             * quantity, the variant is considered low stock.
             */
            'reorder_level' => [
                'sometimes',
                'required',
                'integer',
                'min:0',
                'max:2147483647',
            ],

            /*
             * When enabled, orders may be accepted even when
             * available stock is insufficient.
             */
            'allow_backorder' => [
                'sometimes',
                'required',
                'boolean',
            ],
        ];
    }

    /**
     * Perform additional inventory-setting validation.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(
            function (Validator $validator): void {
                if (
                    ! $this->has('reorder_level')
                    && ! $this->has('allow_backorder')
                ) {
                    $validator->errors()->add(
                        'inventory_settings',
                        'Provide at least one inventory setting to update.'
                    );

                    return;
                }

                $variant = $this->variant();

                if ($variant === null) {
                    return;
                }

                $inventory = $this->inventoryStock($variant);

                if ($inventory === null) {
                    $validator->errors()->add(
                        'inventory_settings',
                        'Inventory has not been initialized for this product variant.'
                    );

                    return;
                }

                /*
                 * Prevent disabling backorders when the current
                 * physical stock is lower than reserved stock.
                 *
                 * In that situation, existing reservations already
                 * depend on the backorder setting.
                 */
                if (
                    $this->has('allow_backorder')
                    && ! $this->boolean('allow_backorder')
                    && (int) $inventory->quantity_on_hand
                        < (int) $inventory->quantity_reserved
                ) {
                    $validator->errors()->add(
                        'allow_backorder',
                        'Backorders cannot be disabled while reserved stock exceeds physical stock.'
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
            'reorder_level.required' =>
                'The reorder level cannot be empty.',

            'reorder_level.integer' =>
                'The reorder level must be a whole number.',

            'reorder_level.min' =>
                'The reorder level cannot be negative.',

            'reorder_level.max' =>
                'The reorder level is too large.',

            'allow_backorder.required' =>
                'The backorder setting cannot be empty.',

            'allow_backorder.boolean' =>
                'The backorder setting must be true or false.',
        ];
    }

    /**
     * Normalize inventory settings before validation.
     */
    protected function prepareForValidation(): void
    {
        $normalized = [];

        if ($this->has('allow_backorder')) {
            $normalized['allow_backorder'] =
                $this->boolean('allow_backorder');
        }

        if ($this->has('reorder_level')) {
            $reorderLevel = $this->input(
                'reorder_level'
            );

            if (is_string($reorderLevel)) {
                $reorderLevel = trim($reorderLevel);
            }

            $normalized['reorder_level'] =
                $reorderLevel;
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
     * Resolve the product variant from route model binding.
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

    /**
     * Resolve inventory for the selected product variant.
     */
    private function inventoryStock(
        ProductVariant $variant
    ): ?InventoryStock {
        if ($variant->relationLoaded('inventoryStock')) {
            return $variant->inventoryStock;
        }

        return InventoryStock::query()
            ->where(
                'product_variant_id',
                $variant->getKey()
            )
            ->first();
    }
}
