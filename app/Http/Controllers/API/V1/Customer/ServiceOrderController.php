<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1\Customer;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Throwable;

class ServiceOrderController extends Controller
{
    private const DELIVERY_PICKUP =
        'pickup_self';

    private const DELIVERY_OWN_MOTO =
        'own_moto';

    private const DELIVERY_NTEZINET_MOTO =
        'ntezinet_moto';

    private const KIGALI_DELIVERY_FEE =
        2000.0;

    public function index(
        Request $request
    ): JsonResponse {
        $orders =
            ServiceOrder::query()
                ->with([
                    'service',
                    'items.service',
                ])
                ->where(
                    'user_id',
                    $request->user()->id
                )
                ->latest('id')
                ->paginate(
                    min(
                        max(
                            (int) $request->input(
                                'per_page',
                                20
                            ),
                            1
                        ),
                        100
                    )
                );

        $orders->through(
            fn (
                ServiceOrder $order
            ): array =>
                $this->orderData(
                    $order
                )
        );

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    public function store(
        Request $request
    ): JsonResponse {
        $data =
            $request->validate([
                'items' => [
                    'required',
                    'array',
                    'min:1',
                    'max:50',
                ],

                'items.*.service_public_id' => [
                    'required',
                    'uuid',
                    'exists:services,public_id',
                ],

                'items.*.mode' => [
                    'required',
                    Rule::in([
                        ServiceOrder::MODE_VOLUME,
                        ServiceOrder::MODE_WEIGHT,
                        ServiceOrder::MODE_AMOUNT,
                    ]),
                ],

                'items.*.quantity' => [
                    'nullable',
                    'numeric',
                    'gt:0',
                ],

                'items.*.unit' => [
                    'required',
                    Rule::in(
                        Service::UNITS
                    ),
                ],

                'items.*.amount_rwf' => [
                    'nullable',
                    'numeric',
                    'gt:0',
                ],

                'delivery_method' => [
                    'nullable',
                    Rule::in([
                        self::DELIVERY_PICKUP,
                        self::DELIVERY_OWN_MOTO,
                        self::DELIVERY_NTEZINET_MOTO,
                    ]),
                ],

                'delivery_address' => [
                    'nullable',
                    'string',
                    'max:2000',
                ],

                'delivery_latitude' => [
                    'nullable',
                    'numeric',
                    'between:-90,90',
                ],

                'delivery_longitude' => [
                    'nullable',
                    'numeric',
                    'between:-180,180',
                ],

                'delivery_city' => [
                    'nullable',
                    'string',
                    'max:150',
                ],

                'delivery_district' => [
                    'nullable',
                    'string',
                    'max:150',
                ],

                'delivery_region' => [
                    'nullable',
                    'string',
                    'max:150',
                ],

                'delivery_country' => [
                    'nullable',
                    'string',
                    'max:150',
                ],

                'is_kigali' => [
                    'nullable',
                    'boolean',
                ],

                'location_note' => [
                    'nullable',
                    'string',
                    'max:1000',
                ],

                'customer_note' => [
                    'nullable',
                    'string',
                    'max:2000',
                ],
            ]);

        foreach (
            $data['items']
            as $index => $item
        ) {
            if (
                $item['mode'] ===
                    ServiceOrder::MODE_AMOUNT
                &&
                empty(
                    $item['amount_rwf']
                )
            ) {
                return $this->validationError(
                    "items.{$index}.amount_rwf",
                    'Amount is required when ordering by money.'
                );
            }

            if (
                $item['mode'] !==
                    ServiceOrder::MODE_AMOUNT
                &&
                empty(
                    $item['quantity']
                )
            ) {
                return $this->validationError(
                    "items.{$index}.quantity",
                    'Quantity is required.'
                );
            }
        }

        $deliveryMethod =
            $data['delivery_method']
            ?? self::DELIVERY_PICKUP;

        if (
            $deliveryMethod !==
                self::DELIVERY_PICKUP
            &&
            (
                empty(
                    $data[
                        'delivery_address'
                    ]
                )
                ||
                ! array_key_exists(
                    'delivery_latitude',
                    $data
                )
                ||
                ! array_key_exists(
                    'delivery_longitude',
                    $data
                )
            )
        ) {
            return $this->validationError(
                'delivery_address',
                'GPS location and delivery address are required for delivery.'
            );
        }

        if (
            $deliveryMethod ===
                self::DELIVERY_NTEZINET_MOTO
            &&
            ! (
                $data['is_kigali']
                ?? false
            )
        ) {
            return $this->validationError(
                'is_kigali',
                'NTEZINET Moto delivery is currently available only inside Kigali.'
            );
        }

        $deliveryFee =
            $deliveryMethod ===
                self::DELIVERY_NTEZINET_MOTO
                ? self::KIGALI_DELIVERY_FEE
                : 0.0;

        try {
            $order =
                DB::transaction(
                    function () use (
                        $request,
                        $data,
                        $deliveryMethod,
                        $deliveryFee
                    ): ServiceOrder {
                        $preparedItems = [];

                        $subtotal = 0.0;

                        foreach (
                            $data['items']
                            as $item
                        ) {
                            $service =
                                Service::query()
                                    ->where(
                                        'public_id',
                                        $item[
                                            'service_public_id'
                                        ]
                                    )
                                    ->lockForUpdate()
                                    ->firstOrFail();

                            $this->ensureAvailable(
                                $service
                            );

                            $mode =
                                $item['mode'];

                            $unit =
                                strtolower(
                                    trim(
                                        $item['unit']
                                    )
                                );

                            $this->validateSaleMode(
                                $service,
                                $mode,
                                $unit
                            );

                            if (
                                $mode ===
                                ServiceOrder::MODE_AMOUNT
                            ) {
                                $amount =
                                    (float) $item[
                                        'amount_rwf'
                                    ];

                                $orderedQuantity =
                                    $service
                                        ->quantityForAmount(
                                            $amount,
                                            $unit
                                        );

                                $lineTotal =
                                    $amount;
                            } else {
                                $orderedQuantity =
                                    (float) $item[
                                        'quantity'
                                    ];

                                $lineTotal =
                                    $service
                                        ->priceFor(
                                            $orderedQuantity,
                                            $unit
                                        );
                            }

                            if (
                                $orderedQuantity <= 0
                                ||
                                $lineTotal <= 0
                            ) {
                                throw new InvalidArgumentException(
                                    'Invalid paint order quantity or price.'
                                );
                            }

                            $stockDeduction =
                                $service
                                    ->convertQuantity(
                                        $orderedQuantity,
                                        $unit,
                                        $service
                                            ->stock_unit
                                    );

                            if (
                                $stockDeduction <= 0
                            ) {
                                throw new InvalidArgumentException(
                                    'Invalid stock quantity.'
                                );
                            }

                            if (
                                $stockDeduction >
                                (float) $service
                                    ->stock_quantity
                            ) {
                                throw new InvalidArgumentException(
                                    sprintf(
                                        '%s does not have enough stock.',
                                        $service->name
                                    )
                                );
                            }

                            $equivalents =
                                $this->equivalents(
                                    $service,
                                    $orderedQuantity,
                                    $unit
                                );

                            $preparedItems[] = [
                                'service_id' =>
                                    $service->id,

                                'service_name' =>
                                    $service->name,

                                'paint_type' =>
                                    $service
                                        ->paint_type,

                                'color_name' =>
                                    $service
                                        ->color_name,

                                'order_mode' =>
                                    $mode,

                                'requested_quantity' =>
                                    $orderedQuantity,

                                'requested_unit' =>
                                    $unit,

                                'requested_amount_rwf' =>
                                    $mode ===
                                    ServiceOrder::MODE_AMOUNT
                                        ? $lineTotal
                                        : null,

                                'equivalent_ml' =>
                                    $equivalents['ml'],

                                'equivalent_l' =>
                                    $equivalents['l'],

                                'equivalent_g' =>
                                    $equivalents['g'],

                                'equivalent_kg' =>
                                    $equivalents['kg'],

                                'reference_quantity' =>
                                    $service
                                        ->reference_quantity,

                                'reference_unit' =>
                                    $service
                                        ->reference_unit,

                                'reference_price_rwf' =>
                                    $service
                                        ->reference_price_rwf,

                                'density_kg_per_l' =>
                                    $service
                                        ->density_kg_per_l,

                                'stock_quantity_deducted' =>
                                    $stockDeduction,

                                'stock_unit' =>
                                    $service
                                        ->stock_unit,

                                'line_total_rwf' =>
                                    round(
                                        $lineTotal,
                                        2
                                    ),
                            ];

                            /*
                             * Reserve stock immediately.
                             * The entire operation is inside
                             * one DB transaction.
                             */
                            $service->update([
                                'stock_quantity' =>
                                    max(
                                        0,
                                        (float) $service
                                            ->stock_quantity
                                        - $stockDeduction
                                    ),
                            ]);

                            $subtotal +=
                                $lineTotal;
                        }

                        if (
                            count(
                                $preparedItems
                            ) === 0
                        ) {
                            throw new InvalidArgumentException(
                                'The order does not contain any paint items.'
                            );
                        }

                        $subtotal =
                            round(
                                $subtotal,
                                2
                            );

                        $grandTotal =
                            round(
                                $subtotal
                                + $deliveryFee,
                                2
                            );

                        $first =
                            $preparedItems[0];

                        $user =
                            $request->user();

                        /*
                         * Legacy paint columns are
                         * populated using the first line.
                         *
                         * New code uses items().
                         */
                        $order =
                            ServiceOrder::query()
                                ->create([
                                    'user_id' =>
                                        $user->id,

                                    'customer_name' =>
                                        $user->name
                                        ?? 'Customer',

                                    'customer_phone' =>
                                        $user->phone
                                        ?? null,

                                    'service_id' =>
                                        $first[
                                            'service_id'
                                        ],

                                    'service_name' =>
                                        $first[
                                            'service_name'
                                        ],

                                    'order_mode' =>
                                        $first[
                                            'order_mode'
                                        ],

                                    'requested_quantity' =>
                                        $first[
                                            'requested_quantity'
                                        ],

                                    'requested_unit' =>
                                        $first[
                                            'requested_unit'
                                        ],

                                    'requested_amount_rwf' =>
                                        $first[
                                            'requested_amount_rwf'
                                        ],

                                    'equivalent_ml' =>
                                        $first[
                                            'equivalent_ml'
                                        ],

                                    'equivalent_l' =>
                                        $first[
                                            'equivalent_l'
                                        ],

                                    'equivalent_g' =>
                                        $first[
                                            'equivalent_g'
                                        ],

                                    'equivalent_kg' =>
                                        $first[
                                            'equivalent_kg'
                                        ],

                                    'reference_quantity' =>
                                        $first[
                                            'reference_quantity'
                                        ],

                                    'reference_unit' =>
                                        $first[
                                            'reference_unit'
                                        ],

                                    'reference_price_rwf' =>
                                        $first[
                                            'reference_price_rwf'
                                        ],

                                    'density_kg_per_l' =>
                                        $first[
                                            'density_kg_per_l'
                                        ],

                                    'stock_quantity_deducted' =>
                                        $first[
                                            'stock_quantity_deducted'
                                        ],

                                    'stock_unit' =>
                                        $first[
                                            'stock_unit'
                                        ],

                                    /*
                                     * Keep old clients useful:
                                     * parent total_price_rwf is
                                     * now the whole checkout total.
                                     */
                                    'total_price_rwf' =>
                                        $grandTotal,

                                    'item_count' =>
                                        count(
                                            $preparedItems
                                        ),

                                    'subtotal_amount_rwf' =>
                                        $subtotal,

                                    'delivery_method' =>
                                        $deliveryMethod,

                                    'delivery_fee_rwf' =>
                                        $deliveryFee,

                                    'total_amount_rwf' =>
                                        $grandTotal,

                                    'delivery_address' =>
                                        $data[
                                            'delivery_address'
                                        ] ?? null,

                                    'delivery_latitude' =>
                                        $data[
                                            'delivery_latitude'
                                        ] ?? null,

                                    'delivery_longitude' =>
                                        $data[
                                            'delivery_longitude'
                                        ] ?? null,

                                    'delivery_city' =>
                                        $data[
                                            'delivery_city'
                                        ] ?? null,

                                    'delivery_district' =>
                                        $data[
                                            'delivery_district'
                                        ] ?? null,

                                    'delivery_region' =>
                                        $data[
                                            'delivery_region'
                                        ] ?? null,

                                    'delivery_country' =>
                                        $data[
                                            'delivery_country'
                                        ] ?? null,

                                    'is_kigali' =>
                                        $data[
                                            'is_kigali'
                                        ] ?? null,

                                    'location_note' =>
                                        $data[
                                            'location_note'
                                        ] ?? null,

                                    'customer_note' =>
                                        $data[
                                            'customer_note'
                                        ] ?? null,

                                    'status' =>
                                        ServiceOrder::
                                        STATUS_PENDING,

                                    'payment_status' =>
                                        ServiceOrder::
                                        PAYMENT_UNPAID,
                                ]);

                        foreach (
                            $preparedItems
                            as $preparedItem
                        ) {
                            $order
                                ->items()
                                ->create(
                                    $preparedItem
                                );
                        }

                        return $order
                            ->load([
                                'service',
                                'items.service',
                            ]);
                    }
                );

            return response()->json(
                [
                    'success' => true,

                    'message' =>
                        'Paint order created successfully.',

                    'data' =>
                        $this->orderData(
                            $order
                        ),
                ],
                201
            );
        } catch (
            InvalidArgumentException $exception
        ) {
            return response()->json(
                [
                    'success' => false,

                    'message' =>
                        $exception
                            ->getMessage(),
                ],
                422
            );
        } catch (
            Throwable $exception
        ) {
            report(
                $exception
            );

            return response()->json(
                [
                    'success' => false,

                    'message' =>
                        'Unable to create paint order.',
                ],
                500
            );
        }
    }

    public function show(
        Request $request,
        ServiceOrder $serviceOrder
    ): JsonResponse {
        abort_unless(
            $serviceOrder->user_id ===
                $request->user()->id,
            404
        );

        $serviceOrder->load([
            'service',
            'items.service',
        ]);

        return response()->json([
            'success' => true,

            'data' =>
                $this->orderData(
                    $serviceOrder
                ),
        ]);
    }

    public function cancel(
        Request $request,
        ServiceOrder $serviceOrder
    ): JsonResponse {
        abort_unless(
            $serviceOrder->user_id ===
                $request->user()->id,
            404
        );

        try {
            $order =
                DB::transaction(
                    function () use (
                        $serviceOrder
                    ): ServiceOrder {
                        $order =
                            ServiceOrder::query()
                                ->whereKey(
                                    $serviceOrder->id
                                )
                                ->with('items')
                                ->lockForUpdate()
                                ->firstOrFail();

                        if (
                            ! in_array(
                                $order->status,
                                [
                                    ServiceOrder::
                                    STATUS_PENDING,

                                    ServiceOrder::
                                    STATUS_CONFIRMED,
                                ],
                                true
                            )
                        ) {
                            throw new InvalidArgumentException(
                                'This order can no longer be cancelled.'
                            );
                        }

                        if (
                            $order->items
                                ->isNotEmpty()
                        ) {
                            foreach (
                                $order->items
                                as $item
                            ) {
                                $service =
                                    Service::query()
                                        ->whereKey(
                                            $item
                                                ->service_id
                                        )
                                        ->lockForUpdate()
                                        ->first();

                                if (
                                    ! $service
                                ) {
                                    continue;
                                }

                                $service->update([
                                    'stock_quantity' =>
                                        (float) $service
                                            ->stock_quantity
                                        +
                                        (float) $item
                                            ->stock_quantity_deducted,
                                ]);
                            }
                        } else {
                            /*
                             * Old single-paint orders.
                             */
                            $service =
                                Service::query()
                                    ->whereKey(
                                        $order
                                            ->service_id
                                    )
                                    ->lockForUpdate()
                                    ->first();

                            if ($service) {
                                $service->update([
                                    'stock_quantity' =>
                                        (float) $service
                                            ->stock_quantity
                                        +
                                        (float) $order
                                            ->stock_quantity_deducted,
                                ]);
                            }
                        }

                        $order->update([
                            'status' =>
                                ServiceOrder::
                                STATUS_CANCELLED,

                            'cancelled_at' =>
                                now(),
                        ]);

                        return $order
                            ->load([
                                'service',
                                'items.service',
                            ]);
                    }
                );

            return response()->json([
                'success' => true,

                'message' =>
                    'Order cancelled successfully.',

                'data' =>
                    $this->orderData(
                        $order
                    ),
            ]);
        } catch (
            InvalidArgumentException $exception
        ) {
            return response()->json(
                [
                    'success' => false,

                    'message' =>
                        $exception
                            ->getMessage(),
                ],
                422
            );
        }
    }

    private function ensureAvailable(
        Service $service
    ): void {
        if (
            $service->service_type !==
                'paint'
            ||
            ! $service->is_active
            ||
            $service->status !==
                'active'
        ) {
            throw new InvalidArgumentException(
                'This paint is not available.'
            );
        }

        if (
            (float) $service
                ->stock_quantity <= 0
        ) {
            throw new InvalidArgumentException(
                'This paint is out of stock.'
            );
        }
    }

    private function validateSaleMode(
        Service $service,
        string $mode,
        string $unit
    ): void {
        $volumeUnits = [
            'ml',
            'l',
        ];

        $weightUnits = [
            'g',
            'kg',
        ];

        if (
            $mode ===
            ServiceOrder::MODE_VOLUME
        ) {
            if (
                ! $service
                    ->allow_volume_sale
            ) {
                throw new InvalidArgumentException(
                    'This paint cannot be ordered by volume.'
                );
            }

            if (
                ! in_array(
                    $unit,
                    $volumeUnits,
                    true
                )
            ) {
                throw new InvalidArgumentException(
                    'Volume orders must use ml or l.'
                );
            }
        }

        if (
            $mode ===
            ServiceOrder::MODE_WEIGHT
        ) {
            if (
                ! $service
                    ->allow_weight_sale
            ) {
                throw new InvalidArgumentException(
                    'This paint cannot be ordered by weight.'
                );
            }

            if (
                ! in_array(
                    $unit,
                    $weightUnits,
                    true
                )
            ) {
                throw new InvalidArgumentException(
                    'Weight orders must use g or kg.'
                );
            }
        }

        if (
            $mode ===
            ServiceOrder::MODE_AMOUNT
        ) {
            if (
                ! $service
                    ->allow_amount_sale
            ) {
                throw new InvalidArgumentException(
                    'This paint cannot be ordered by RWF amount.'
                );
            }

            if (
                in_array(
                    $unit,
                    $volumeUnits,
                    true
                )
                &&
                ! $service
                    ->allow_volume_sale
            ) {
                throw new InvalidArgumentException(
                    'Volume conversion is not available for this paint.'
                );
            }

            if (
                in_array(
                    $unit,
                    $weightUnits,
                    true
                )
                &&
                ! $service
                    ->allow_weight_sale
            ) {
                throw new InvalidArgumentException(
                    'Weight conversion is not available for this paint.'
                );
            }
        }
    }

    /**
     * Return all useful equivalents.
     */
    private function equivalents(
        Service $service,
        float $quantity,
        string $unit
    ): array {
        return [
            'ml' =>
                $this->convertOrNull(
                    $service,
                    $quantity,
                    $unit,
                    'ml'
                ),

            'l' =>
                $this->convertOrNull(
                    $service,
                    $quantity,
                    $unit,
                    'l'
                ),

            'g' =>
                $this->convertOrNull(
                    $service,
                    $quantity,
                    $unit,
                    'g'
                ),

            'kg' =>
                $this->convertOrNull(
                    $service,
                    $quantity,
                    $unit,
                    'kg'
                ),
        ];
    }

    private function convertOrNull(
        Service $service,
        float $quantity,
        string $fromUnit,
        string $toUnit
    ): ?float {
        try {
            return round(
                $service
                    ->convertQuantity(
                        $quantity,
                        $fromUnit,
                        $toUnit
                    ),
                6
            );
        } catch (
            InvalidArgumentException
        ) {
            return null;
        }
    }

    private function validationError(
        string $field,
        string $message
    ): JsonResponse {
        return response()->json(
            [
                'message' =>
                    'The given data was invalid.',

                'errors' => [
                    $field => [
                        $message,
                    ],
                ],
            ],
            422
        );
    }

    private function orderData(
        ServiceOrder $order
    ): array {
        $total =
            (float) (
                $order
                    ->total_amount_rwf
                ??
                $order
                    ->total_price_rwf
                ??
                0
            );

        $subtotal =
            (float) (
                $order
                    ->subtotal_amount_rwf
                ??
                $order
                    ->total_price_rwf
                ??
                0
            );

        $items =
            $order->items
                ->map(
                    function ($item): array {
                        return [
                            'public_id' =>
                                (string) $item
                                    ->public_id,

                            'service_public_id' =>
                                $item
                                    ->service
                                    ? (string) $item
                                        ->service
                                        ->public_id
                                    : null,

                            'service_name' =>
                                $item
                                    ->service_name,

                            'name' =>
                                $item
                                    ->service_name,

                            'paint_type' =>
                                $item
                                    ->paint_type,

                            'color_name' =>
                                $item
                                    ->color_name,

                            'color' =>
                                $item
                                    ->color_name,

                            'order_mode' =>
                                $item
                                    ->order_mode,

                            'requested_quantity' =>
                                $item
                                    ->requested_quantity,

                            'requested_unit' =>
                                $item
                                    ->requested_unit,

                            'requested_amount_rwf' =>
                                $item
                                    ->requested_amount_rwf,

                            'equivalent_ml' =>
                                $item
                                    ->equivalent_ml,

                            'equivalent_l' =>
                                $item
                                    ->equivalent_l,

                            'equivalent_g' =>
                                $item
                                    ->equivalent_g,

                            'equivalent_kg' =>
                                $item
                                    ->equivalent_kg,

                            'reference_quantity' =>
                                $item
                                    ->reference_quantity,

                            'reference_unit' =>
                                $item
                                    ->reference_unit,

                            'reference_price_rwf' =>
                                $item
                                    ->reference_price_rwf,

                            'density_kg_per_l' =>
                                $item
                                    ->density_kg_per_l,

                            'stock_unit' =>
                                $item
                                    ->stock_unit,

                            'line_total_rwf' =>
                                $item
                                    ->line_total_rwf,

                            'line_total' =>
                                $item
                                    ->line_total_rwf,
                        ];
                    }
                )
                ->values()
                ->all();

        /*
         * Backward-compatible old order.
         */
        if (
            count($items) === 0
            &&
            $order->service_id
        ) {
            $items[] = [
                'service_public_id' =>
                    $order->service
                        ? (string) $order
                            ->service
                            ->public_id
                        : null,

                'service_name' =>
                    $order
                        ->service_name,

                'name' =>
                    $order
                        ->service_name,

                'paint_type' =>
                    $order->service
                        ?->paint_type,

                'color_name' =>
                    $order->service
                        ?->color_name,

                'order_mode' =>
                    $order
                        ->order_mode,

                'requested_quantity' =>
                    $order
                        ->requested_quantity,

                'requested_unit' =>
                    $order
                        ->requested_unit,

                'requested_amount_rwf' =>
                    $order
                        ->requested_amount_rwf,

                'equivalent_ml' =>
                    $order
                        ->equivalent_ml,

                'equivalent_l' =>
                    $order
                        ->equivalent_l,

                'equivalent_g' =>
                    $order
                        ->equivalent_g,

                'equivalent_kg' =>
                    $order
                        ->equivalent_kg,

                'line_total_rwf' =>
                    $order
                        ->total_price_rwf,
            ];
        }

        return [
            /*
             * Mobile understands both.
             */
            'id' =>
                (string) $order
                    ->public_id,

            'public_id' =>
                (string) $order
                    ->public_id,

            'order_number' =>
                $order
                    ->order_number,

            'status' =>
                $order
                    ->status,

            'order_status' =>
                $order
                    ->status,

            'payment_status' =>
                $order
                    ->payment_status,

            'currency' =>
                'RWF',

            'item_count' =>
                count(
                    $items
                ),

            'subtotal_amount' =>
                $subtotal,

            'subtotal_amount_rwf' =>
                $subtotal,

            'delivery_method' =>
                $order
                    ->delivery_method,

            'delivery_fee' =>
                (float) (
                    $order
                        ->delivery_fee_rwf
                    ?? 0
                ),

            'delivery_fee_rwf' =>
                (float) (
                    $order
                        ->delivery_fee_rwf
                    ?? 0
                ),

            'total_amount' =>
                $total,

            'total_amount_rwf' =>
                $total,

            'total_price_rwf' =>
                $total,

            'delivery_address' =>
                $order
                    ->delivery_address,

            'delivery_latitude' =>
                $order
                    ->delivery_latitude,

            'delivery_longitude' =>
                $order
                    ->delivery_longitude,

            'delivery_city' =>
                $order
                    ->delivery_city,

            'delivery_district' =>
                $order
                    ->delivery_district,

            'delivery_region' =>
                $order
                    ->delivery_region,

            'delivery_country' =>
                $order
                    ->delivery_country,

            'is_kigali' =>
                $order
                    ->is_kigali,

            'location_note' =>
                $order
                    ->location_note,

            'customer_note' =>
                $order
                    ->customer_note,

            'items' =>
                $items,

            'created_at' =>
                $order
                    ->created_at
                    ?->toISOString(),

            'updated_at' =>
                $order
                    ->updated_at
                    ?->toISOString(),
        ];
    }
}
