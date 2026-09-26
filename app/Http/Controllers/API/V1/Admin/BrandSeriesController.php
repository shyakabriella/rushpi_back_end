<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\BrandSeries;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class BrandSeriesController extends Controller
{
    public function index(
        Request $request,
        Brand $brand
    ): JsonResponse {
        $this->authorizeAdministrator($request);

        $series = $brand->series()
            ->withCount('models')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $series,
        ]);
    }

    public function store(
        Request $request,
        Brand $brand
    ): JsonResponse {
        $this->authorizeAdministrator($request);

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('brand_series', 'name')
                    ->where(
                        fn ($query) =>
                            $query->where(
                                'brand_id',
                                $brand->id
                            )
                    )
                    ->withoutTrashed(),
            ],
            'slug' => [
                'nullable',
                'string',
                'max:180',
                Rule::unique('brand_series', 'slug')
                    ->where(
                        fn ($query) =>
                            $query->where(
                                'brand_id',
                                $brand->id
                            )
                    )
                    ->withoutTrashed(),
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'is_active' => [
                'sometimes',
                'boolean',
            ],
            'sort_order' => [
                'sometimes',
                'integer',
                'min:0',
            ],
        ]);

        $series = $brand->series()->create($data);
        $series->loadCount('models');

        return response()->json([
            'success' => true,
            'message' =>
                'Brand series created successfully.',
            'data' => $series,
        ], 201);
    }

    public function update(
        Request $request,
        Brand $brand,
        BrandSeries $series
    ): JsonResponse {
        $this->authorizeAdministrator($request);
        $this->ensureSeriesBelongsToBrand(
            $brand,
            $series
        );

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
                Rule::unique('brand_series', 'name')
                    ->where(
                        fn ($query) =>
                            $query->where(
                                'brand_id',
                                $brand->id
                            )
                    )
                    ->ignore($series->id)
                    ->withoutTrashed(),
            ],
            'slug' => [
                'nullable',
                'string',
                'max:180',
                Rule::unique('brand_series', 'slug')
                    ->where(
                        fn ($query) =>
                            $query->where(
                                'brand_id',
                                $brand->id
                            )
                    )
                    ->ignore($series->id)
                    ->withoutTrashed(),
            ],
            'description' => [
                'nullable',
                'string',
            ],
            'is_active' => [
                'sometimes',
                'boolean',
            ],
            'sort_order' => [
                'sometimes',
                'integer',
                'min:0',
            ],
        ]);

        $series->update($data);
        $series->refresh()->loadCount('models');

        return response()->json([
            'success' => true,
            'message' =>
                'Brand series updated successfully.',
            'data' => $series,
        ]);
    }

    public function destroy(
        Request $request,
        Brand $brand,
        BrandSeries $series
    ): JsonResponse {
        $this->authorizeAdministrator($request);
        $this->ensureSeriesBelongsToBrand(
            $brand,
            $series
        );

        if ($series->models()->exists()) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Delete the models in this series first.',
            ], 409);
        }

        $series->delete();

        return response()->json([
            'success' => true,
            'message' =>
                'Brand series deleted successfully.',
        ]);
    }

    private function ensureSeriesBelongsToBrand(
        Brand $brand,
        BrandSeries $series
    ): void {
        abort_unless(
            $series->brand_id === $brand->id,
            404
        );
    }

    private function authorizeAdministrator(
        Request $request
    ): void {
        abort_unless(
            $request->user()?->hasAnyRole([
                'admin',
                'superadmin',
            ]),
            403,
            'Only administrators can manage brand series.'
        );
    }
}
