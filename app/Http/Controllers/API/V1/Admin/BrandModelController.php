<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\BrandModel;
use App\Models\BrandSeries;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class BrandModelController extends Controller
{
    public function index(
        Request $request,
        Brand $brand,
        BrandSeries $series
    ): JsonResponse {
        $this->authorizeAdministrator($request);
        $this->ensureSeriesBelongsToBrand(
            $brand,
            $series
        );

        $models = $series->models()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $models,
        ]);
    }

    public function store(
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
                'max:180',
                Rule::unique('brand_models', 'name')
                    ->where(
                        fn ($query) =>
                            $query->where(
                                'brand_series_id',
                                $series->id
                            )
                    )
                    ->withoutTrashed(),
            ],
            'slug' => [
                'nullable',
                'string',
                'max:200',
                Rule::unique('brand_models', 'slug')
                    ->where(
                        fn ($query) =>
                            $query->where(
                                'brand_series_id',
                                $series->id
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

        $model = $series->models()->create([
            ...$data,
            'brand_id' => $brand->id,
        ]);

        return response()->json([
            'success' => true,
            'message' =>
                'Brand model created successfully.',
            'data' => $model,
        ], 201);
    }

    public function update(
        Request $request,
        Brand $brand,
        BrandSeries $series,
        BrandModel $model
    ): JsonResponse {
        $this->authorizeAdministrator($request);
        $this->ensureModelBelongsToSeries(
            $brand,
            $series,
            $model
        );

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:180',
                Rule::unique('brand_models', 'name')
                    ->where(
                        fn ($query) =>
                            $query->where(
                                'brand_series_id',
                                $series->id
                            )
                    )
                    ->ignore($model->id)
                    ->withoutTrashed(),
            ],
            'slug' => [
                'nullable',
                'string',
                'max:200',
                Rule::unique('brand_models', 'slug')
                    ->where(
                        fn ($query) =>
                            $query->where(
                                'brand_series_id',
                                $series->id
                            )
                    )
                    ->ignore($model->id)
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

        $model->update($data);
        $model->refresh();

        return response()->json([
            'success' => true,
            'message' =>
                'Brand model updated successfully.',
            'data' => $model,
        ]);
    }

    public function destroy(
        Request $request,
        Brand $brand,
        BrandSeries $series,
        BrandModel $model
    ): JsonResponse {
        $this->authorizeAdministrator($request);
        $this->ensureModelBelongsToSeries(
            $brand,
            $series,
            $model
        );

        $model->delete();

        return response()->json([
            'success' => true,
            'message' =>
                'Brand model deleted successfully.',
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

    private function ensureModelBelongsToSeries(
        Brand $brand,
        BrandSeries $series,
        BrandModel $model
    ): void {
        $this->ensureSeriesBelongsToBrand(
            $brand,
            $series
        );

        abort_unless(
            $model->brand_id === $brand->id
            && $model->brand_series_id === $series->id,
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
            'Only administrators can manage brand models.'
        );
    }
}
