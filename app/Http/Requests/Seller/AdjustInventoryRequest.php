<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Enums\StockMovementType;
use App\Models\InventoryStock;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SellerProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AdjustInventoryRequest extends FormRequest
{
    /**
     * Only an active owner or manager of an approved seller
     * may adjust stock for variants belonging to their business.
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
         * Inventory changes are operational changes.
         *
         * Stock may continue changing while a product is approved
         * and visible in the public catalog.
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
     * Validation rules for adjusting inventory.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            /*
             * Examples:
             *
             *  20 = add twenty units
             *  -5 = remove five units
             */
            'quantity' => [
                'required',
                'integer',
                'not_in:0',
                'min:-2147483648',
                'max:2147483647',
            ],

            'movement_type' => [
                'required',
                Rule::enum(StockMovementType::class),
            ],

            /*
             * Every manual stock adjustment must explain why
             * the quantity was changed.
             */
            'reason' => [
                'required',
                'string',
                'min:3',
                'max:1000',
            ],

            /*
             * Optional reference information can connect the
             * movement to a purchase, return, correction or audit.
             */
            'reference_type' => [
                'nullable',
                'string',
                'max:100',
                'regex:/^[A-Za-z0-9._-]+$/',
            ],

            'reference_id' => [
                'nullable',
                'string',
                'max:150',
            ],

            /*
             * Additional non-sensitive information about the
             * adjustment may be stored in the movement record.
             */
            'metadata' => [
                'nullable',
                'array',
                'max:50',
            ],

            'metadata.*' => [
                'nullable',
            ],
        ];
    }

    /**
     * Validate the resulting inventory quantity.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $variant = $this->variant();

                if ($variant === null) {
                    return;
                }

                $inventory = $this->inventoryStock($variant);

                if ($inventory === null) {
                    $validator->errors()->add(
                        'quantity',
                        'Inventory has not been initialized for this product variant.'
                    );

                    return;
                }

                $quantityChange = (int) $this->input(
                    'quantity'
                );

                $currentQuantity = (int) $inventory
                    ->quantity_on_hand;

                $reservedQuantity = (int) $inventory
                    ->quantity_reserved;

                $resultingQuantity =
                    $currentQuantity + $quantityChange;

                /*
                 * Physical stock cannot become negative.
                 */
                if ($resultingQuantity < 0) {
                    $validator->errors()->add(
                        'quantity',
                        sprintf(
                            'This adjustment would make stock negative. Current stock is %d and the lowest permitted adjustment is -%d.',
                            $currentQuantity,
                            $currentQuantity
                        )
                    );

                    return;
                }

                /*
                 * Reserved stock must remain protected when
                 * backorders are not enabled.
                 */
                if (
                    ! (bool) $inventory->allow_backorder
                    && $resultingQuantity < $reservedQuantity
                ) {
                    $maximumRemoval = max(
                        $currentQuantity - $reservedQuantity,
                        0
                    );

                    $validator->errors()->add(
                        'quantity',
                        sprintf(
                            'This adjustment would reduce stock below the reserved quantity of %d. You may currently remove at most %d unit(s).',
                            $reservedQuantity,
                            $maximumRemoval
                        )
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
            'quantity.required' =>
                'The stock adjustment quantity is required.',

            'quantity.integer' =>
                'The stock adjustment quantity must be a whole number.',

            'quantity.not_in' =>
                'The stock adjustment quantity cannot be zero.',

            'quantity.min' =>
                'The stock adjustment quantity is too small.',

            'quantity.max' =>
                'The stock adjustment quantity is too large.',

            'movement_type.required' =>
                'The stock movement type is required.',

            'movement_type.enum' =>
                'The selected stock movement type is invalid.',

            'reason.required' =>
                'A reason for the stock adjustment is required.',

            'reason.min' =>
                'The stock adjustment reason must contain at least 3 characters.',

            'reason.max' =>
                'The stock adjustment reason may not exceed 1,000 characters.',

            'reference_type.regex' =>
                'The reference type may contain only letters, numbers, dots, dashes and underscores.',

            'reference_type.max' =>
                'The reference type may not exceed 100 characters.',

            'reference_id.max' =>
                'The reference ID may not exceed 150 characters.',

            'metadata.array' =>
                'The stock adjustment metadata must be an object or array.',

            'metadata.max' =>
                'The stock adjustment metadata may contain at most 50 values.',
        ];
    }

    /**
     * Normalize stock adjustment data before validation.
     */
    protected function prepareForValidation(): void
    {
        $normalized = [];

        if ($this->has('movement_type')) {
            $movementType = $this->input(
                'movement_type'
            );

            $normalized['movement_type'] =
                is_string($movementType)
                    ? strtolower(trim($movementType))
                    : $movementType;
        }

        if ($this->has('reason')) {
            $reason = $this->input('reason');

            $normalized['reason'] =
                is_string($reason)
                    ? trim($reason)
                    : $reason;
        }

        foreach ([
            'reference_type',
            'reference_id',
        ] as $field) {
            if (! $this->has($field)) {
                continue;
            }

            $value = $this->input($field);

            if (is_string($value)) {
                $value = trim($value);
            }

            if ($value === '') {
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
     * Resolve the inventory stock record for the variant.
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
