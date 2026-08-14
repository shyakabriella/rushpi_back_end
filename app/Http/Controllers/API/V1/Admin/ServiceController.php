<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use InvalidArgumentException;

class ServiceController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | LIST SERVICES
    |--------------------------------------------------------------------------
    */
    public function index(
        Request $request
    ): JsonResponse {
        if (
            ! $this->isAdministrator(
                $request
            )
        ) {
            return $this->forbiddenResponse();
        }

        $perPage = min(
            max(
                (int) $request->input(
                    'per_page',
                    15
                ),
                1
            ),
            100
        );

        $query =
            Service::query()
                ->latest();

        $search =
            trim(
                (string) $request->input(
                    'q',
                    ''
                )
            );

        if ($search !== '') {
            $query->where(
                function ($builder) use (
                    $search
                ): void {
                    $builder
                        ->where(
                            'name',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'paint_type',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'brand_name',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'color_name',
                            'like',
                            "%{$search}%"
                        );
                }
            );
        }

        if (
            $request->has(
                'is_active'
            )
        ) {
            $query->where(
                'is_active',
                $request->boolean(
                    'is_active'
                )
            );
        }

        return response()->json([
            'success' => true,

            'message' =>
                'Services retrieved successfully.',

            'data' =>
                $query->paginate(
                    $perPage
                ),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE PAINT SERVICE
    |--------------------------------------------------------------------------
    */
    public function store(
        Request $request
    ): JsonResponse {
        if (
            ! $this->isAdministrator(
                $request
            )
        ) {
            return $this->forbiddenResponse();
        }

        $data =
            $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'service_type' => [
                    'nullable',
                    'string',
                    'max:50',
                ],

                'paint_type' => [
                    'nullable',
                    'string',
                    'max:150',
                ],

                'brand_name' => [
                    'nullable',
                    'string',
                    'max:150',
                ],

                'color_name' => [
                    'nullable',
                    'string',
                    'max:150',
                ],

                'description' => [
                    'nullable',
                    'string',
                    'max:5000',
                ],

                'reference_quantity' => [
                    'required',
                    'numeric',
                    'gt:0',
                ],

                'reference_unit' => [
                    'required',
                    Rule::in(
                        Service::UNITS
                    ),
                ],

                'reference_price_rwf' => [
                    'required',
                    'numeric',
                    'gt:0',
                ],

                'density_kg_per_l' => [
                    'nullable',
                    'numeric',
                    'gt:0',
                ],

                'allow_volume_sale' => [
                    'nullable',
                    'boolean',
                ],

                'allow_weight_sale' => [
                    'nullable',
                    'boolean',
                ],

                'allow_amount_sale' => [
                    'nullable',
                    'boolean',
                ],

                'stock_quantity' => [
                    'required',
                    'numeric',
                    'gte:0',
                ],

                'stock_unit' => [
                    'required',
                    Rule::in(
                        Service::UNITS
                    ),
                ],

                'image' => [
                    'nullable',
                    'image',
                    'mimes:jpg,jpeg,png,webp',
                    'max:5120',
                ],

                'is_active' => [
                    'nullable',
                    'boolean',
                ],
            ]);

        $allowVolume =
            $request->boolean(
                'allow_volume_sale',
                true
            );

        $allowWeight =
            $request->boolean(
                'allow_weight_sale',
                false
            );

        if (
            $allowVolume
            &&
            $allowWeight
            &&
            empty(
                $data[
                    'density_kg_per_l'
                ]
            )
        ) {
            return response()->json(
                [
                    'success' => false,

                    'message' =>
                        'Validation Error.',

                    'errors' => [
                        'density_kg_per_l' => [
                            'Density is required when paint can be sold by both weight and volume.',
                        ],
                    ],
                ],
                422
            );
        }

        $data['service_type'] =
            $data['service_type']
            ?? 'paint';

        $data['allow_volume_sale'] =
            $allowVolume;

        $data['allow_weight_sale'] =
            $allowWeight;

        $data['allow_amount_sale'] =
            $request->boolean(
                'allow_amount_sale',
                true
            );

        $data['is_active'] =
            $request->boolean(
                'is_active',
                true
            );

        $data['status'] =
            'active';

        if (
            $request->hasFile(
                'image'
            )
        ) {
            $data['image_path'] =
                $request
                    ->file('image')
                    ->store(
                        'services/paints',
                        'public'
                    );
        }

        unset(
            $data['image']
        );

        $service =
            Service::query()
                ->create(
                    $data
                );

        return response()->json(
            [
                'success' => true,

                'message' =>
                    'Paint service created successfully.',

                'data' =>
                    $service->fresh(),
            ],
            201
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW
    |--------------------------------------------------------------------------
    */
    public function show(
        Request $request,
        Service $service
    ): JsonResponse {
        if (
            ! $this->isAdministrator(
                $request
            )
        ) {
            return $this->forbiddenResponse();
        }

        return response()->json([
            'success' => true,

            'data' => $service,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */
    public function update(
        Request $request,
        Service $service
    ): JsonResponse {
        if (
            ! $this->isAdministrator(
                $request
            )
        ) {
            return $this->forbiddenResponse();
        }

        $data =
            $request->validate([
                'name' => [
                    'sometimes',
                    'required',
                    'string',
                    'max:255',
                ],

                'paint_type' => [
                    'sometimes',
                    'nullable',
                    'string',
                    'max:150',
                ],

                'brand_name' => [
                    'sometimes',
                    'nullable',
                    'string',
                    'max:150',
                ],

                'color_name' => [
                    'sometimes',
                    'nullable',
                    'string',
                    'max:150',
                ],

                'description' => [
                    'sometimes',
                    'nullable',
                    'string',
                    'max:5000',
                ],

                'reference_quantity' => [
                    'sometimes',
                    'numeric',
                    'gt:0',
                ],

                'reference_unit' => [
                    'sometimes',
                    Rule::in(
                        Service::UNITS
                    ),
                ],

                'reference_price_rwf' => [
                    'sometimes',
                    'numeric',
                    'gt:0',
                ],

                'density_kg_per_l' => [
                    'sometimes',
                    'nullable',
                    'numeric',
                    'gt:0',
                ],

                'allow_volume_sale' => [
                    'sometimes',
                    'boolean',
                ],

                'allow_weight_sale' => [
                    'sometimes',
                    'boolean',
                ],

                'allow_amount_sale' => [
                    'sometimes',
                    'boolean',
                ],

                'stock_quantity' => [
                    'sometimes',
                    'numeric',
                    'gte:0',
                ],

                'stock_unit' => [
                    'sometimes',
                    Rule::in(
                        Service::UNITS
                    ),
                ],

                'image' => [
                    'sometimes',
                    'nullable',
                    'image',
                    'mimes:jpg,jpeg,png,webp',
                    'max:5120',
                ],

                'is_active' => [
                    'sometimes',
                    'boolean',
                ],
            ]);

        $allowVolume =
            array_key_exists(
                'allow_volume_sale',
                $data
            )
                ? (bool) $data[
                    'allow_volume_sale'
                ]
                : $service
                    ->allow_volume_sale;

        $allowWeight =
            array_key_exists(
                'allow_weight_sale',
                $data
            )
                ? (bool) $data[
                    'allow_weight_sale'
                ]
                : $service
                    ->allow_weight_sale;

        $density =
            $data[
                'density_kg_per_l'
            ]
            ??
            $service
                ->density_kg_per_l;

        if (
            $allowVolume
            &&
            $allowWeight
            &&
            (float) $density <= 0
        ) {
            return response()->json(
                [
                    'success' => false,

                    'message' =>
                        'Validation Error.',

                    'errors' => [
                        'density_kg_per_l' => [
                            'Density is required when paint can be sold by both weight and volume.',
                        ],
                    ],
                ],
                422
            );
        }

        if (
            $request->hasFile(
                'image'
            )
        ) {
            if (
                $service->image_path
            ) {
                Storage::disk(
                    'public'
                )->delete(
                    $service
                        ->image_path
                );
            }

            $data['image_path'] =
                $request
                    ->file('image')
                    ->store(
                        'services/paints',
                        'public'
                    );
        }

        unset(
            $data['image']
        );

        $service->update(
            $data
        );

        return response()->json([
            'success' => true,

            'message' =>
                'Paint service updated successfully.',

            'data' =>
                $service->fresh(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    */
    public function destroy(
        Request $request,
        Service $service
    ): JsonResponse {
        if (
            ! $this->isAdministrator(
                $request
            )
        ) {
            return $this->forbiddenResponse();
        }

        if (
            $service->image_path
        ) {
            Storage::disk(
                'public'
            )->delete(
                $service->image_path
            );
        }

        $service->delete();

        return response()->json([
            'success' => true,

            'message' =>
                'Service deleted successfully.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | QUOTE / CALCULATOR
    |--------------------------------------------------------------------------
    |
    | By quantity:
    |
    | {
    |   "mode": "quantity",
    |   "quantity": 350,
    |   "unit": "ml"
    | }
    |
    | By money:
    |
    | {
    |   "mode": "amount",
    |   "amount_rwf": 1000,
    |   "unit": "ml"
    | }
    |
    */
    public function quote(
        Request $request,
        Service $service
    ): JsonResponse {
        if (
            ! $this->isAdministrator(
                $request
            )
        ) {
            return $this->forbiddenResponse();
        }

        $data =
            $request->validate([
                'mode' => [
                    'required',
                    Rule::in([
                        'quantity',
                        'amount',
                    ]),
                ],

                'quantity' => [
                    'nullable',
                    'numeric',
                    'gt:0',
                ],

                'amount_rwf' => [
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
            ]);

        try {
            if (
                $data['mode']
                ===
                'amount'
            ) {
                if (
                    empty(
                        $data[
                            'amount_rwf'
                        ]
                    )
                ) {
                    return response()->json(
                        [
                            'success' => false,

                            'message' =>
                                'amount_rwf is required.',
                        ],
                        422
                    );
                }

                $quote =
                    $service
                        ->quoteAmount(
                            (float) $data[
                                'amount_rwf'
                            ],
                            $data[
                                'unit'
                            ]
                        );
            } else {
                if (
                    empty(
                        $data[
                            'quantity'
                        ]
                    )
                ) {
                    return response()->json(
                        [
                            'success' => false,

                            'message' =>
                                'quantity is required.',
                        ],
                        422
                    );
                }

                $quote =
                    $service
                        ->quote(
                            (float) $data[
                                'quantity'
                            ],
                            $data[
                                'unit'
                            ]
                        );
            }

            return response()->json([
                'success' => true,

                'data' => [
                    'service' => [
                        'public_id' =>
                            $service
                                ->public_id,

                        'name' =>
                            $service
                                ->name,

                        'reference' =>
                            $service
                                ->reference_label,

                        'density_kg_per_l' =>
                            $service
                                ->density_kg_per_l,
                    ],

                    'quote' =>
                        $quote,
                ],
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
}