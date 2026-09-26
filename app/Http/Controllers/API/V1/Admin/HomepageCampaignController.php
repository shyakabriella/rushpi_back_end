<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\HomepageCampaign\StoreHomepageCampaignRequest;
use App\Http\Requests\Admin\HomepageCampaign\UpdateHomepageCampaignRequest;
use App\Http\Resources\HomepageCampaignResource;
use App\Models\HomepageCampaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class HomepageCampaignController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(
            max((int) $request->input('per_page', 20), 1),
            100
        );

        $query = HomepageCampaign::query();

        $search = trim((string) $request->input('q'));

        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                $query
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('subtitle', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where(
                'is_active',
                $request->boolean('is_active')
            );
        }

        $campaigns = $query
            ->ordered()
            ->paginate($perPage)
            ->withQueryString();

        return HomepageCampaignResource::collection($campaigns)
            ->additional([
                'success' => true,
                'message' => 'Homepage campaigns retrieved successfully.',
            ])
            ->response();
    }

    public function store(
        StoreHomepageCampaignRequest $request
    ): JsonResponse {
        $storedPaths = [];

        try {
            $data = $request->validated();

            $data['desktop_image_path'] =
                $this->storeImage(
                    $request->file('desktop_image')
                );

            $storedPaths[] =
                $data['desktop_image_path'];

            if ($request->hasFile('mobile_image')) {
                $data['mobile_image_path'] =
                    $this->storeImage(
                        $request->file('mobile_image')
                    );

                $storedPaths[] =
                    $data['mobile_image_path'];
            }

            unset(
                $data['desktop_image'],
                $data['mobile_image']
            );

            if (($data['link_type'] ?? null) === 'none') {
                $data['link_value'] = null;
            }

            $campaign = DB::transaction(
                fn (): HomepageCampaign => HomepageCampaign::query()->create($data)
            );

            return response()->json([
                'success' => true,
                'message' => 'Homepage campaign created successfully.',
                'data' => new HomepageCampaignResource($campaign),
            ], 201);
        } catch (Throwable $exception) {
            foreach ($storedPaths as $path) {
                Storage::disk('public')->delete($path);
            }

            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Unable to create the homepage campaign.',
                'data' => null,
            ], 500);
        }
    }

    public function show(
        HomepageCampaign $homepageCampaign
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => 'Homepage campaign retrieved successfully.',
            'data' => new HomepageCampaignResource(
                $homepageCampaign
            ),
        ]);
    }

    public function update(
        UpdateHomepageCampaignRequest $request,
        HomepageCampaign $homepageCampaign
    ): JsonResponse {
        $newPaths = [];
        $oldPaths = [];

        try {
            $data = $request->validated();

            if ($request->hasFile('desktop_image')) {
                $data['desktop_image_path'] =
                    $this->storeImage(
                        $request->file('desktop_image')
                    );

                $newPaths[] =
                    $data['desktop_image_path'];

                $oldPaths[] =
                    $homepageCampaign->desktop_image_path;
            }

            if ($request->hasFile('mobile_image')) {
                $data['mobile_image_path'] =
                    $this->storeImage(
                        $request->file('mobile_image')
                    );

                $newPaths[] =
                    $data['mobile_image_path'];

                if ($homepageCampaign->mobile_image_path) {
                    $oldPaths[] =
                        $homepageCampaign->mobile_image_path;
                }
            } elseif (
                ($data['remove_mobile_image'] ?? false)
                && $homepageCampaign->mobile_image_path
            ) {
                $oldPaths[] =
                    $homepageCampaign->mobile_image_path;

                $data['mobile_image_path'] = null;
            }

            unset(
                $data['desktop_image'],
                $data['mobile_image'],
                $data['remove_mobile_image']
            );

            if (($data['link_type'] ?? null) === 'none') {
                $data['link_value'] = null;
            }

            DB::transaction(
                function () use (
                    $homepageCampaign,
                    $data
                ): void {
                    $homepageCampaign->update($data);
                }
            );

            foreach ($oldPaths as $path) {
                Storage::disk('public')->delete($path);
            }

            return response()->json([
                'success' => true,
                'message' => 'Homepage campaign updated successfully.',
                'data' => new HomepageCampaignResource(
                    $homepageCampaign->fresh()
                ),
            ]);
        } catch (Throwable $exception) {
            foreach ($newPaths as $path) {
                Storage::disk('public')->delete($path);
            }

            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Unable to update the homepage campaign.',
                'data' => null,
            ], 500);
        }
    }

    public function destroy(
        HomepageCampaign $homepageCampaign
    ): JsonResponse {
        try {
            $paths = array_filter([
                $homepageCampaign->desktop_image_path,
                $homepageCampaign->mobile_image_path,
            ]);

            DB::transaction(
                fn () => $homepageCampaign->delete()
            );

            foreach ($paths as $path) {
                Storage::disk('public')->delete($path);
            }

            return response()->json([
                'success' => true,
                'message' => 'Homepage campaign deleted successfully.',
                'data' => null,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,
                'message' => 'Unable to delete the homepage campaign.',
                'data' => null,
            ], 500);
        }
    }

    private function storeImage(
        ?UploadedFile $image
    ): string {
        if (! $image instanceof UploadedFile) {
            throw new \RuntimeException(
                'The campaign image is missing.'
            );
        }

        $extension = strtolower(
            $image->extension()
        );

        $filename = sprintf(
            '%s.%s',
            (string) Str::ulid(),
            $extension
        );

        $path = Storage::disk('public')->putFileAs(
            'homepage-campaigns',
            $image,
            $filename
        );

        if (! is_string($path) || $path === '') {
            throw new \RuntimeException(
                'The campaign image could not be stored.'
            );
        }

        return $path;
    }
}
