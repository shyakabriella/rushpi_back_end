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
    /**
     * Customer's paint orders.
     */
    public function index(
        Request $request
    ): JsonResponse {
        $orders = ServiceOrder::query()
            ->with('service')
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

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    /**
     * Create paint order.
     */
    public function store(
        Request $request
    ): JsonResponse {
        $data = $request->validate([
            'service_public_id' => [
                'required',
                'uuid',
                'exists:services,public_id',
            ],

            'mode' => [
                'required',
                Rule::in([
                    ServiceOrder::MODE_VOLUME,
                    ServiceOrder::MODE_WEIGHT,
                    ServiceOrder::MODE_AMOUNT,
                ]),
            ],

            'quantity' => [
                'nullable',
                'numeric',
                'gt:0',
            ],

            'unit' => [
                'required',
                Rule::in(
                    Service::UNITS
                ),
            ],

            'amount_rwf' => [
                'nullable',
                'numeric',
                'gt:0',
            ],

            'delivery_address' => [
                'nullable',
                'string',
                'max:2000',
            ],

            'customer_note' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        if (
            $data['mode'] !==
                ServiceOrder::MODE_AMOUNT &&
            empty($data['quantity'])
        ) {
            return $this->validationError(
                'quantity',
                'Quantity is required.'
            );
        }

        if (
            $data['mode'] ===
                ServiceOrder::MODE_AMOUNT &&
            empty($data['amount_rwf'])
        ) {
            return $this->validationError(
                'amount_rwf',
                'Amount is required.'
            );
        }

        try {
            $order = DB::transaction(
                function () use (
                    $request,
                    $data
                ): ServiceOrder {
                    $service = Service::query()
                        ->where(
                            'public_id',
                            $data[
                                'service_public_id'
                            ]
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                    $this->ensureAvailable(
                        $service
                    );

                    $this->validateSaleMode(
                        $service,
                        $data['mode'],
                        $data['unit']
                    );

                    $quote =
                        $this->calculateQuote(
                            $service,
                            $data
                        );

                    $orderedQuantity =
                        $data['mode'] ===
                        ServiceOrder::MODE_AMOUNT
                            ? (float) $quote[
                                'quantity'
                            ]
                            : (float) $data[
                                'quantity'
                            ];

                    $stockDeduction =
                        $service
                            ->convertQuantity(
                                $orderedQuantity,
                                $data['unit'],
                                $service->stock_unit
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
                            'Requested paint quantity is greater than available stock.'
                        );
                    }

                    $total =
                        $data['mode'] ===
                        ServiceOrder::MODE_AMOUNT
                            ? (float) $data[
                                'amount_rwf'
                            ]
                            : (float) (
                                $quote[
                                    'price_rwf'
                                ] ?? 0
                            );

                    if ($total <= 0) {
                        throw new InvalidArgumentException(
                            'Invalid order price.'
                        );
                    }

                    $user =
                        $request->user();

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
                                    $service->id,

                                'service_name' =>
                                    $service->name,

                                'order_mode' =>
                                    $data['mode'],

                                'requested_quantity' =>
                                    $orderedQuantity,

                                'requested_unit' =>
                                    $data['unit'],

                                'requested_amount_rwf' =>
                                    $data['mode'] ===
                                    ServiceOrder::MODE_AMOUNT
                                        ? $data[
                                            'amount_rwf'
                                        ]
                                        : null,

                                'equivalent_ml' =>
                                    $quote[
                                        'equivalent_ml'
                                    ] ?? null,

                                'equivalent_l' =>
                                    $quote[
                                        'equivalent_l'
                                    ] ?? null,

                                'equivalent_g' =>
                                    $quote[
                                        'equivalent_g'
                                    ] ?? null,

                                'equivalent_kg' =>
                                    $quote[
                                        'equivalent_kg'
                                    ] ?? null,

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

                                'total_price_rwf' =>
                                    $total,

                                'status' =>
                                    ServiceOrder::
                                    STATUS_PENDING,

                                'payment_status' =>
                                    ServiceOrder::
                                    PAYMENT_UNPAID,

                                'delivery_address' =>
                                    $data[
                                        'delivery_address'
                                    ] ?? null,

                                'customer_note' =>
                                    $data[
                                        'customer_note'
                                    ] ?? null,
                            ]);

                    /*
                     * Reserve/deduct stock immediately.
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

                    return $order;
                }
            );

            return response()->json(
                [
                    'success' => true,
                    'message' =>
                        'Paint order created successfully.',
                    'data' =>
                        $order->load(
                            'service'
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
        } catch (Throwable $exception) {
            report($exception);

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

    /**
     * Show one customer order.
     */
    public function show(
        Request $request,
        ServiceOrder $serviceOrder
    ): JsonResponse {
        abort_unless(
            $serviceOrder->user_id ===
                $request->user()->id,
            404
        );

        return response()->json([
            'success' => true,
            'data' =>
                $serviceOrder->load(
                    'service'
                ),
        ]);
    }

    /**
     * Customer cancels a pending order.
     *
     * Stock is returned.
     */
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
            $order = DB::transaction(
                function () use (
                    $serviceOrder
                ): ServiceOrder {
                    $order =
                        ServiceOrder::query()
                            ->whereKey(
                                $serviceOrder->id
                            )
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

                    $service =
                        Service::query()
                            ->whereKey(
                                $order->service_id
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

                    $order->update([
                        'status' =>
                            ServiceOrder::
                            STATUS_CANCELLED,

                        'cancelled_at' =>
                            now(),
                    ]);

                    return $order;
                }
            );

            return response()->json([
                'success' => true,
                'message' =>
                    'Order cancelled successfully.',
                'data' => $order,
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
                'paint' ||
            ! $service->is_active ||
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
                ) &&
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
                ) &&
                ! $service
                    ->allow_weight_sale
            ) {
                throw new InvalidArgumentException(
                    'Weight conversion is not available for this paint.'
                );
            }
        }
    }

    private function calculateQuote(
        Service $service,
        array $data
    ): array {
        if (
            $data['mode'] ===
            ServiceOrder::MODE_AMOUNT
        ) {
            return $service->quoteAmount(
                (float) $data[
                    'amount_rwf'
                ],
                $data['unit']
            );
        }

        return $service->quote(
            (float) $data[
                'quantity'
            ],
            $data['unit']
        );
    }

    private function validationError(
        string $field,
        string $message
    ): JsonResponse {
        return response()->json(
            [
                'success' => false,
                'message' =>
                    'Validation Error.',
                'errors' => [
                    $field => [
                        $message,
                    ],
                ],
            ],
            422
        );
    }
}
