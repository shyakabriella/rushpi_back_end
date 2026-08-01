<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\StockMovementType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\StockMovement
 */
class SellerStockMovementResource extends JsonResource
{
    /**
     * Transform a stock movement into an API response.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $quantity = (int) $this->quantity;

        return [
            'public_id' => $this->public_id,

            'movement_type' =>
                $this->movementTypeValue(),

            'movement_type_label' =>
                $this->movementTypeLabel(),

            /*
             * Quantity is stored as a signed value.
             *
             * Positive: stock was added.
             * Negative: stock was removed.
             */
            'quantity' => $quantity,

            'absolute_quantity' => abs($quantity),

            'direction' => match (true) {
                $quantity > 0 => 'increase',
                $quantity < 0 => 'decrease',
                default => 'unchanged',
            },

            /*
             * Physical stock values before and after
             * this immutable inventory movement.
             */
            'stock' => [
                'quantity_on_hand_before' =>
                    (int) $this->quantity_on_hand_before,

                'quantity_on_hand_after' =>
                    (int) $this->quantity_on_hand_after,

                'quantity_on_hand_change' =>
                    (int) $this->quantity_on_hand_after
                    - (int) $this->quantity_on_hand_before,

                'quantity_reserved_before' =>
                    (int) $this->quantity_reserved_before,

                'quantity_reserved_after' =>
                    (int) $this->quantity_reserved_after,

                'quantity_reserved_change' =>
                    (int) $this->quantity_reserved_after
                    - (int) $this->quantity_reserved_before,

                'available_before' =>
                    (int) $this->quantity_on_hand_before
                    - (int) $this->quantity_reserved_before,

                'available_after' =>
                    (int) $this->quantity_on_hand_after
                    - (int) $this->quantity_reserved_after,
            ],

            'reason' => $this->reason,

            /*
             * Optional reference to an order, purchase,
             * return, stock audit or another business record.
             */
            'reference' => [
                'type' => $this->reference_type,

                'id' => $this->reference_id,
            ],

            'metadata' => $this->metadata ?? [],

            /*
             * Variant information is returned only when
             * the controller loads the relationship.
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

                        'barcode' =>
                            $this->variant->barcode,

                        'name' =>
                            $this->variant->name,

                        'attributes' =>
                            $this->variant->attributes ?? [],

                        'is_default' =>
                            (bool) $this->variant->is_default,

                        'is_active' =>
                            (bool) $this->variant->is_active,
                    ];
                }
            ),

            /*
             * Product information is returned when the
             * nested variant.product relationship is loaded.
             */
            'product' => $this->when(
                $this->relationLoaded('variant')
                && $this->variant !== null
                && $this->variant->relationLoaded('product'),
                function (): ?array {
                    $product = $this->variant?->product;

                    if ($product === null) {
                        return null;
                    }

                    return [
                        'public_id' =>
                            $product->public_id,

                        'name' =>
                            $product->name,

                        'slug' =>
                            $product->slug,

                        'status' =>
                            $product->status?->value
                            ?? $product->status,
                    ];
                }
            ),

            /*
             * Seller information is returned only when
             * the seller profile relationship is loaded.
             */
            'seller' => $this->whenLoaded(
                'sellerProfile',
                function (): ?array {
                    if ($this->sellerProfile === null) {
                        return null;
                    }

                    return [
                        'public_id' =>
                            $this->sellerProfile->public_id,

                        'legal_business_name' =>
                            $this->sellerProfile
                                ->legal_business_name,

                        'trading_name' =>
                            $this->sellerProfile
                                ->trading_name,
                    ];
                }
            ),

            /*
             * User who performed the stock operation.
             */
            'performed_by' => $this->whenLoaded(
                'performedBy',
                function (): ?array {
                    if ($this->performedBy === null) {
                        return null;
                    }

                    return [
                        'public_id' =>
                            $this->performedBy->public_id,

                        'name' =>
                            $this->performedBy->name,

                        'email' =>
                            $this->performedBy->email,
                    ];
                }
            ),

            /*
             * Stock movement records are immutable and normally
             * contain created_at only.
             */
            'created_at' =>
                $this->created_at?->toISOString(),
        ];
    }

    /**
     * Return the stock movement enum value.
     */
    private function movementTypeValue(): ?string
    {
        if (
            $this->movement_type
            instanceof StockMovementType
        ) {
            return $this->movement_type->value;
        }

        return is_string($this->movement_type)
            ? $this->movement_type
            : null;
    }

    /**
     * Return a readable movement-type label.
     */
    private function movementTypeLabel(): ?string
    {
        if (
            $this->movement_type
            instanceof StockMovementType
        ) {
            return $this->movement_type->label();
        }

        if (is_string($this->movement_type)) {
            return StockMovementType::tryFrom(
                $this->movement_type
            )?->label();
        }

        return null;
    }
}
