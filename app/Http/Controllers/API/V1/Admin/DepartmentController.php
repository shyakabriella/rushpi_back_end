<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreBrandRequest;
use App\Http\Requests\Admin\UpdateBrandRequest;
use App\Http\Resources\BrandResource;
use App\Models\Brand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

final class BrandController extends Controller
{
    /**
     * Display a paginated list of marketplace brands.
     */
    public function index(
        Request $request
    ): JsonResponse {
        $validated = $request->validate([
            'q' => [
                'nullable',
                'string',
                'max:150',
            ],

            'is_active' => [
                'nullable',
                'boolean',
            ],

            'sort_by' => [
                'nullable',
                Rule::in([
                    'sort_order',
                    'name',
                    'created_at',
                    'updated_at',
                ]),
            ],

            'sort_direction' => [
                'nullable',
                Rule::in([
                    'asc',
                    'desc',
                ]),
            ],

            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ]);

        $query = Brand::query()
            ->withCount('products');

        /*
         * Search brands.
         */
        if (
            isset($validated['q'])
            && trim(
                (string) $validated['q']
            ) !== ''
        ) {
            $query->search(
                (string) $validated['q']
            );
        }

        /*
         * Filter by active/inactive status.
         */
        if (
            array_key_exists(
                'is_active',
                $validated
            )
        ) {
            $query->where(
                'is_active',
                $request->boolean(
                    'is_active'
                )
            );
        }

        /*
         * Sorting.
         */
        $sortBy = $validated['sort_by']
            ?? 'sort_order';

        $sortDirection =
            $validated['sort_direction']
            ?? 'asc';

        $query->orderBy(
            $sortBy,
            $sortDirection
        );

        /*
         * Always keep brand names deterministic
         * when sorting by another column.
         */
        if ($sortBy !== 'name') {
            $query->orderBy('name');
        }

        $brands = $query
            ->paginate(
                (int) (
                    $validated['per_page']
                    ?? 15
                )
            )
            ->withQueryString();

        return BrandResource::collection(
            $brands
        )
            ->additional([
                'success' => true,

                'message' =>
                    'Brands retrieved successfully.',
            ])
            ->response();
    }

    /**
     * Create a marketplace brand.
     */
    public function store(
        StoreBrandRequest $request
    ): JsonResponse {
        try {
            $brand = DB::transaction(
                function () use (
                    $request
                ): Brand {
                    $data =
                        $request->validated();

                    /*
                     * Allow Brand model to generate
                     * the slug when none is supplied.
                     */
                    if (
                        array_key_exists(
                            'slug',
                            $data
                        )
                        && blank(
                            $data['slug']
                        )
                    ) {
                        unset(
                            $data['slug']
                        );
                    }

                    return Brand::query()
                        ->create(
                            $data
                        );
                }
            );

            $brand->loadCount(
                'products'
            );

            return response()->json([
                'success' => true,

                'message' =>
                    'Brand created successfully.',

                'data' =>
                    new BrandResource(
                        $brand
                    ),
            ], 201);
        } catch (
            Throwable $exception
        ) {
            report(
                $exception
            );

            return response()->json([
                'success' => false,

                'message' =>
                    'Unable to create the brand.',

                'data' => null,
            ], 500);
        }
    }

    /**
     * Display one marketplace brand.
     */
    public function show(
        Request $request,
        Brand $brand
    ): JsonResponse {
        $brand->loadCount(
            'products'
        );

        return response()->json([
            'success' => true,

            'message' =>
                'Brand retrieved successfully.',

            'data' =>
                new BrandResource(
                    $brand
                ),
        ]);
    }

    /**
     * Update a marketplace brand.
     */
    public function update(
        UpdateBrandRequest $request,
        Brand $brand
    ): JsonResponse {
        $data =
            $request->validated();

        /*
         * If slug is blank, leave slug generation
         * to the Brand model.
         */
        if (
            array_key_exists(
                'slug',
                $data
            )
            && blank(
                $data['slug']
            )
        ) {
            unset(
                $data['slug']
            );
        }

        try {
            DB::transaction(
                function () use (
                    $brand,
                    $data
                ): void {
                    $brand->update(
                        $data
                    );
                }
            );

            $brand->refresh();

            $brand->loadCount(
                'products'
            );

            return response()->json([
                'success' => true,

                'message' =>
                    'Brand updated successfully.',

                'data' =>
                    new BrandResource(
                        $brand
                    ),
            ]);
        } catch (
            Throwable $exception
        ) {
            report(
                $exception
            );

            return response()->json([
                'success' => false,

                'message' =>
                    'Unable to update the brand.',

                'data' => null,
            ], 500);
        }
    }

    /**
     * Soft-delete a marketplace brand.
     */
    public function destroy(
        Request $request,
        Brand $brand
    ): JsonResponse {
        /*
         * Do not remove brands already used
         * by marketplace products.
         */
        if (
            $brand
                ->products()
                ->exists()
        ) {
            return response()->json([
                'success' => false,

                'message' =>
                    'This brand cannot be deleted while products are assigned to it.',

                'data' => null,
            ], 409);
        }

        try {
            $brand->delete();

            return response()->json([
                'success' => true,

                'message' =>
                    'Brand deleted successfully.',

                'data' => null,
            ]);
        } catch (
            Throwable $exception
        ) {
            report(
                $exception
            );

            return response()->json([
                'success' => false,

                'message' =>
                    'Unable to delete the brand.',

                'data' => null,
            ], 500);
        }
    }
}