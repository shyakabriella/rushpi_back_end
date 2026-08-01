<?php

declare(strict_types=1);

namespace App\Enums;

enum StockMovementType: string
{
    case INITIAL_STOCK = 'initial_stock';

    case RESTOCK = 'restock';

    case MANUAL_ADJUSTMENT = 'manual_adjustment';

    case RESERVATION = 'reservation';

    case RESERVATION_RELEASE = 'reservation_release';

    case SALE = 'sale';

    case ORDER_CANCELLED = 'order_cancelled';

    case CUSTOMER_RETURN = 'customer_return';

    case DAMAGED = 'damaged';

    case LOST = 'lost';

    case CORRECTION = 'correction';

    /**
     * Return a readable stock movement name.
     */
    public function label(): string
    {
        return match ($this) {
            self::INITIAL_STOCK => 'Initial Stock',
            self::RESTOCK => 'Restock',
            self::MANUAL_ADJUSTMENT => 'Manual Adjustment',
            self::RESERVATION => 'Stock Reservation',
            self::RESERVATION_RELEASE => 'Reservation Release',
            self::SALE => 'Sale',
            self::ORDER_CANCELLED => 'Order Cancelled',
            self::CUSTOMER_RETURN => 'Customer Return',
            self::DAMAGED => 'Damaged Stock',
            self::LOST => 'Lost Stock',
            self::CORRECTION => 'Stock Correction',
        };
    }

    /**
     * Determine whether this movement increases stock.
     */
    public function increasesStock(): bool
    {
        return in_array($this, [
            self::INITIAL_STOCK,
            self::RESTOCK,
            self::ORDER_CANCELLED,
            self::CUSTOMER_RETURN,
        ], true);
    }

    /**
     * Determine whether this movement decreases stock.
     */
    public function decreasesStock(): bool
    {
        return in_array($this, [
            self::SALE,
            self::DAMAGED,
            self::LOST,
        ], true);
    }

    /**
     * Determine whether this movement changes reserved stock.
     */
    public function affectsReservation(): bool
    {
        return in_array($this, [
            self::RESERVATION,
            self::RESERVATION_RELEASE,
        ], true);
    }

    /**
     * Return all stock movement values.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $type): string => $type->value,
            self::cases()
        );
    }
}
