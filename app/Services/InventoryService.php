<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\InventoryStock;
use App\Models\ProductVariant;
use App\Models\SellerProfile;
use App\Models\StockMovement;
use App\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventoryService
{
    /**
     * Adjust physical stock and create an immutable movement record.
     *
     * A positive quantity adds stock.
     * A negative quantity removes stock.
     *
     * @param array<string, mixed> $metadata
     *
     * @return array{
     *     inventory: InventoryStock,
     *     movement: StockMovement
     * }
     */
    public function adjustStock(
        ProductVariant $variant,
        SellerProfile $sellerProfile,
        ?User $performedBy,
        StockMovementType|string $movementType,
        int $quantity,
        string $reason,
        ?string $referenceType = null,
        ?string $referenceId = null,
        array $metadata = []
    ): array {
        if ($quantity === 0) {
            throw new InvalidArgumentException(
                'The inventory adjustment quantity cannot be zero.'
            );
        }

        $reason = trim($reason);

        if ($reason === '') {
            throw new InvalidArgumentException(
                'A reason for the inventory adjustment is required.'
            );
        }

        $resolvedMovementType = $this->resolveMovementType(
            $movementType
        );

        return DB::transaction(
            function () use (
                $variant,
                $sellerProfile,
                $performedBy,
                $resolvedMovementType,
                $quantity,
                $reason,
                $referenceType,
                $referenceId,
                $metadata
            ): array {
                /*
                 * Lock the variant first.
                 *
                 * Every inventory operation should lock records in
                 * the same order to reduce database deadlocks.
                 */
                $lockedVariant = ProductVariant::query()
                    ->with([
                        'product:id,seller_profile_id',
                    ])
                    ->whereKey($variant->getKey())
                    ->lockForUpdate()
                    ->first();

                if ($lockedVariant === null) {
                    throw new ModelNotFoundException(
                        'The product variant was not found.'
                    );
                }

                $product = $lockedVariant->product;

                if ($product === null) {
                    throw new DomainException(
                        'The product variant is not connected to a product.'
                    );
                }

                /*
                 * Defence-in-depth ownership check.
                 *
                 * Controllers and form requests already perform this
                 * check, but critical services should verify it again.
                 */
                if (
                    (int) $product->seller_profile_id
                    !== (int) $sellerProfile->getKey()
                ) {
                    throw new DomainException(
                        'The product variant does not belong to this seller business.'
                    );
                }

                /*
                 * Lock the inventory record so two requests cannot
                 * read and update the same quantity simultaneously.
                 */
                $inventory = InventoryStock::query()
                    ->where(
                        'product_variant_id',
                        $lockedVariant->getKey()
                    )
                    ->lockForUpdate()
                    ->first();

                if ($inventory === null) {
                    throw new DomainException(
                        'Inventory has not been initialized for this product variant.'
                    );
                }

                $quantityOnHandBefore =
                    (int) $inventory->quantity_on_hand;

                $quantityReservedBefore =
                    (int) $inventory->quantity_reserved;

                $quantityOnHandAfter =
                    $quantityOnHandBefore + $quantity;

                /*
                 * Physical stock must never become negative.
                 */
                if ($quantityOnHandAfter < 0) {
                    throw new DomainException(
                        sprintf(
                            'Insufficient stock. Current physical stock is %d unit(s), but the requested adjustment would reduce it below zero.',
                            $quantityOnHandBefore
                        )
                    );
                }

                /*
                 * When backorders are disabled, physical stock must
                 * remain sufficient to cover all reserved units.
                 */
                if (
                    ! (bool) $inventory->allow_backorder
                    && $quantityOnHandAfter
                        < $quantityReservedBefore
                ) {
                    throw new DomainException(
                        sprintf(
                            'This adjustment would reduce stock below the reserved quantity of %d unit(s).',
                            $quantityReservedBefore
                        )
                    );
                }

                $inventory->quantity_on_hand =
                    $quantityOnHandAfter;

                $inventory->save();

                /*
                 * Stock movement records are permanent audit records.
                 * They must never be updated or deleted afterward.
                 */
                $movement = new StockMovement();

                $movement->product_variant_id =
                    $lockedVariant->getKey();

                $movement->seller_profile_id =
                    $sellerProfile->getKey();

                $movement->performed_by =
                    $performedBy?->getKey();

                $movement->movement_type =
                    $resolvedMovementType;

                $movement->quantity =
                    $quantity;

                $movement->quantity_on_hand_before =
                    $quantityOnHandBefore;

                $movement->quantity_on_hand_after =
                    $quantityOnHandAfter;

                $movement->quantity_reserved_before =
                    $quantityReservedBefore;

                $movement->quantity_reserved_after =
                    $quantityReservedBefore;

                $movement->reference_type =
                    $this->nullableTrimmedString(
                        $referenceType
                    );

                $movement->reference_id =
                    $this->nullableTrimmedString(
                        $referenceId
                    );

                $movement->reason =
                    $reason;

                $movement->metadata =
                    $metadata !== []
                        ? $metadata
                        : null;

                $movement->save();

                $inventory->refresh();
                $movement->refresh();

                return [
                    'inventory' => $inventory,
                    'movement' => $movement,
                ];
            },

            /*
             * Laravel will retry the transaction when a database
             * deadlock occurs.
             */
            5
        );
    }

    /**
     * Update inventory controls without changing stock quantities.
     */
    public function updateSettings(
        ProductVariant $variant,
        SellerProfile $sellerProfile,
        ?int $reorderLevel = null,
        ?bool $allowBackorder = null
    ): InventoryStock {
        if (
            $reorderLevel === null
            && $allowBackorder === null
        ) {
            throw new InvalidArgumentException(
                'At least one inventory setting must be provided.'
            );
        }

        if (
            $reorderLevel !== null
            && $reorderLevel < 0
        ) {
            throw new InvalidArgumentException(
                'The inventory reorder level cannot be negative.'
            );
        }

        return DB::transaction(
            function () use (
                $variant,
                $sellerProfile,
                $reorderLevel,
                $allowBackorder
            ): InventoryStock {
                $lockedVariant = ProductVariant::query()
                    ->with([
                        'product:id,seller_profile_id',
                    ])
                    ->whereKey($variant->getKey())
                    ->lockForUpdate()
                    ->first();

                if ($lockedVariant === null) {
                    throw new ModelNotFoundException(
                        'The product variant was not found.'
                    );
                }

                $product = $lockedVariant->product;

                if ($product === null) {
                    throw new DomainException(
                        'The product variant is not connected to a product.'
                    );
                }

                if (
                    (int) $product->seller_profile_id
                    !== (int) $sellerProfile->getKey()
                ) {
                    throw new DomainException(
                        'The product variant does not belong to this seller business.'
                    );
                }

                $inventory = InventoryStock::query()
                    ->where(
                        'product_variant_id',
                        $lockedVariant->getKey()
                    )
                    ->lockForUpdate()
                    ->first();

                if ($inventory === null) {
                    throw new DomainException(
                        'Inventory has not been initialized for this product variant.'
                    );
                }

                /*
                 * Backorders cannot be disabled when existing
                 * reservations already exceed physical stock.
                 */
                if (
                    $allowBackorder === false
                    && (int) $inventory->quantity_reserved
                        > (int) $inventory->quantity_on_hand
                ) {
                    throw new DomainException(
                        'Backorders cannot be disabled while reserved stock exceeds physical stock.'
                    );
                }

                if ($reorderLevel !== null) {
                    $inventory->reorder_level =
                        $reorderLevel;
                }

                if ($allowBackorder !== null) {
                    $inventory->allow_backorder =
                        $allowBackorder;
                }

                $inventory->save();
                $inventory->refresh();

                return $inventory;
            },
            5
        );
    }

    /**
     * Resolve a movement type from an enum or string value.
     */
    private function resolveMovementType(
        StockMovementType|string $movementType
    ): StockMovementType {
        if ($movementType instanceof StockMovementType) {
            return $movementType;
        }

        $normalizedMovementType = strtolower(
            trim($movementType)
        );

        $resolvedMovementType =
            StockMovementType::tryFrom(
                $normalizedMovementType
            );

        if ($resolvedMovementType === null) {
            throw new InvalidArgumentException(
                sprintf(
                    'The stock movement type "%s" is invalid.',
                    $movementType
                )
            );
        }

        return $resolvedMovementType;
    }

    /**
     * Normalize an optional string value.
     */
    private function nullableTrimmedString(
        ?string $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value !== ''
            ? $value
            : null;
    }
}
