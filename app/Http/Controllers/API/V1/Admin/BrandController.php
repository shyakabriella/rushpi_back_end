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
use Throwable;

class BrandController extends Controller
{
    /**
     * Display a paginated list of product brands.
     */
    public function index(Request $request): JsonResponse
    {
        if (! $this->isAdministrator($request)) {
            return $this->forbiddenResponse();
        }

        $perPage = min(
            max((int) $request->input('per_page', 15), 1),
            100
        );

        $query = Brand::query()
            ->withCount('products');

        $search = trim((string) $request->input('q'));

        if ($search !== '') {
            $query->search($search);
        }

        if ($request->has('is_active')) {
            $query->where(
                'is_active',
                $request->boolean('is_active')
            );
        }

        $brands = $query
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return BrandResource::collection($brands)
            ->additional([
                'success' => true,
                'message' => 'Brands retrieved successfully.',
            ])
            ->response();
    }

    /**
     * Create a new product brand.
     */
    public function store(
        StoreBrandRequest $request
    ): JsonResponse {
        try {
            $brand = DB::transaction(
                function () use ($request): Brand {
                    $data = $request->validated();

                    /*
                     * When the slug is empty, allow the Brand model
                     * to generate a unique slug automatically.
                     */
                    if (
                        array_key_exists('slug', $data)
                        && blank($data['slug'])
                    ) {
                        unset($data['slug']);
                    }

                    return Brand::query()->create($data);
                }
            );

            $brand->loadCount('products');

            return response()->json([
                'success' => true,
                'message' => 'Brand created successfully.',
                'data' => new BrandResource($brand),
            ], 201);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Unable to create the brand.',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Display one product brand.
     */
    public function show(
        Request $request,
        Brand $brand
    ): JsonResponse {
        if (! $this->isAdministrator($request)) {
            return $this->forbiddenResponse();
        }

        $brand->loadCount('products');

        return response()->json([
            'success' => true,
            'message' => 'Brand retrieved successfully.',
            'data' => new BrandResource($brand),
        ]);
    }

    /**
     * Update an existing product brand.
     */
    public function update(
        UpdateBrandRequest $request,
        Brand $brand
    ): JsonResponse {
        try {
            $data = $request->validated();

            /*
             * When an empty slug is submitted, keep the current slug.
             *
             * When the brand name changes and the slug is omitted,
             * the Brand model generates a new unique slug.
             */
            if (
                array_key_exists('slug', $data)
                && blank($data['slug'])
            ) {
                unset($data['slug']);
            }

            DB::transaction(
                function () use ($brand, $data): void {
                    $brand->update($data);
                }
            );

            $brand->refresh();
            $brand->loadCount('products');

            return response()->json([
                'success' => true,
                'message' => 'Brand updated successfully.',
                'data' => new BrandResource($brand),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Unable to update the brand.',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Soft-delete a product brand.
     */
    public function destroy(
        Request $request,
        Brand $brand
    ): JsonResponse {
        if (! $this->isAdministrator($request)) {
            return $this->forbiddenResponse();
        }

        /*
         * A brand cannot be deleted while products
         * are still assigned to it.
         */
        if ($brand->products()->exists()) {
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
                'message' => 'Brand deleted successfully.',
                'data' => null,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Unable to delete the brand.',
                'data' => null,
            ], 500);
        }
    }

    /**
     * Check whether the authenticated user is
     * an administrator or super administrator.
     */
    private function isAdministrator(Request $request): bool
    {
        $user = $request->user();

        return $user !== null
            && $user->hasAnyRole([
                'admin',
                'superadmin',
            ]);
    }

    /**
     * Return a standard forbidden response.
     */
    private function forbiddenResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' =>
                'Only administrators can manage product brands.',
            'data' => null,
        ], 403);
    }
}
