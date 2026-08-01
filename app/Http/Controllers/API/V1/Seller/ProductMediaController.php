<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1\Seller;

use App\Enums\MediaType;
use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\StoreProductMediaRequest;
use App\Http\Resources\SellerProductMediaResource;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\ProductVariant;
use App\Models\SellerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

class ProductMediaController extends Controller
{
    /**
     * Display media belonging to one seller product.
     */
    public function index(
        Request $request,
        SellerProfile $sellerProfile,
        Product $product
    ): JsonResponse {
        if (! $this->canManageMedia($request, $sellerProfile)) {
            return $this->forbiddenResponse();
        }

        if (! $this->productBelongsToSeller(
            $product,
            $sellerProfile
        )) {
            return $this->productNotFoundResponse();
        }

        $media = ProductMedia::query()
            ->where('product_id', $product->getKey())
            ->with([
                'product:id,public_id,name,slug',
                'variant:id,public_id,sku,name',
                'uploadedBy:id,public_id,name,email',
            ])
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,
            'message' =>
                'Product media retrieved successfully.',

            'data' =>
                SellerProductMediaResource::collection($media),
        ]);
    }

    /**
     * Upload a new product image.
     */
    public function store(
        StoreProductMediaRequest $request,
        SellerProfile $sellerProfile,
        Product $product
    ): JsonResponse {
        if (! $this->productBelongsToSeller(
            $product,
            $sellerProfile
        )) {
            return $this->productNotFoundResponse();
        }

        $uploadedFile = $request->file('image');

        if ($uploadedFile === null) {
            return response()->json([
                'success' => false,
                'message' =>
                    'The product image was not received.',

                'data' => null,
            ], 422);
        }

        $disk = 'public';

        $extension = strtolower(
            $uploadedFile->extension()
            ?: $uploadedFile->getClientOriginalExtension()
        );

        $filename = Str::ulid()->toBase32()
            .'.'.$extension;

        $directory = sprintf(
            'products/%s/%s',
            $sellerProfile->public_id,
            $product->public_id
        );

        $storedPath = null;

        try {
            $storedPath = $uploadedFile->storeAs(
                $directory,
                $filename,
                $disk
            );

            if ($storedPath === false) {
                throw new \RuntimeException(
                    'The product image could not be stored.'
                );
            }

            $media = DB::transaction(
                function () use (
                    $request,
                    $product,
                    $uploadedFile,
                    $disk,
                    $storedPath,
                    $extension
                ): ProductMedia {
                    $data = $request->validated();

                    $variant = null;

                    if (! empty(
                        $data['product_variant_public_id']
                    )) {
                        $variant = ProductVariant::query()
                            ->where(
                                'public_id',
                                $data[
                                    'product_variant_public_id'
                                ]
                            )
                            ->where(
                                'product_id',
                                $product->getKey()
                            )
                            ->firstOrFail();
                    }

                    $hasExistingMedia = $product
                        ->media()
                        ->exists();

                    $shouldBePrimary =
                        ! $hasExistingMedia
                        || (bool) (
                            $data['is_primary'] ?? false
                        );

                    if ($shouldBePrimary) {
                        $product->media()->update([
                            'is_primary' => false,
                        ]);
                    }

                    $media = new ProductMedia();

                    $media->product_id =
                        $product->getKey();

                    $media->product_variant_id =
                        $variant?->getKey();

                    $media->uploaded_by =
                        $request->user()?->getKey();

                    $media->media_type =
                        MediaType::IMAGE;

                    $media->disk = $disk;

                    $media->path = $storedPath;

                    $media->original_name =
                        $uploadedFile
                            ->getClientOriginalName();

                    $media->mime_type =
                        $uploadedFile->getMimeType()
                        ?? $uploadedFile
                            ->getClientMimeType();

                    $media->extension = $extension;

                    $media->size_bytes =
                        $uploadedFile->getSize();

                    $media->alt_text =
                        $data['alt_text'] ?? null;

                    $media->sort_order =
                        (int) ($data['sort_order'] ?? 0);

                    $media->is_primary =
                        $shouldBePrimary;

                    $media->save();

                    $this->resetProductModeration(
                        product: $product,
                        userId: $request
                            ->user()
                            ?->getKey()
                    );

                    return $media;
                }
            );

            $this->loadMediaRelations($media);

            return response()->json([
                'success' => true,

                'message' =>
                    'Product image uploaded successfully.',

                'data' =>
                    new SellerProductMediaResource($media),
            ], 201);
        } catch (Throwable $exception) {
            if (
                is_string($storedPath)
                && $storedPath !== ''
            ) {
                Storage::disk($disk)->delete($storedPath);
            }

            report($exception);

            return response()->json([
                'success' => false,

                'message' =>
                    'Unable to upload the product image.',

                'data' => null,
            ], 500);
        }
    }

    /**
     * Set one media item as the primary product image.
     */
    public function setPrimary(
        Request $request,
        SellerProfile $sellerProfile,
        Product $product,
        ProductMedia $media
    ): JsonResponse {
        if (! $this->canManageMedia(
            $request,
            $sellerProfile
        )) {
            return $this->forbiddenResponse();
        }

        if (! $this->productBelongsToSeller(
            $product,
            $sellerProfile
        )) {
            return $this->productNotFoundResponse();
        }

        if (! $this->mediaBelongsToProduct(
            $media,
            $product
        )) {
            return $this->mediaNotFoundResponse();
        }

        if (! $product->canBeEditedBySeller()) {
            return $this->productNotEditableResponse();
        }

        if ($media->is_primary) {
            $this->loadMediaRelations($media);

            return response()->json([
                'success' => true,

                'message' =>
                    'This image is already the primary image.',

                'data' =>
                    new SellerProductMediaResource($media),
            ]);
        }

        try {
            DB::transaction(
                function () use (
                    $request,
                    $product,
                    $media
                ): void {
                    $product->media()->update([
                        'is_primary' => false,
                    ]);

                    $media->is_primary = true;
                    $media->save();

                    $this->resetProductModeration(
                        product: $product,
                        userId: $request
                            ->user()
                            ?->getKey()
                    );
                }
            );

            $media->refresh();

            $this->loadMediaRelations($media);

            return response()->json([
                'success' => true,

                'message' =>
                    'Primary product image updated successfully.',

                'data' =>
                    new SellerProductMediaResource($media),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,

                'message' =>
                    'Unable to set the primary product image.',

                'data' => null,
            ], 500);
        }
    }

    /**
     * Update the display order of product media.
     */
    public function reorder(
        Request $request,
        SellerProfile $sellerProfile,
        Product $product
    ): JsonResponse {
        if (! $this->canManageMedia(
            $request,
            $sellerProfile
        )) {
            return $this->forbiddenResponse();
        }

        if (! $this->productBelongsToSeller(
            $product,
            $sellerProfile
        )) {
            return $this->productNotFoundResponse();
        }

        if (! $product->canBeEditedBySeller()) {
            return $this->productNotEditableResponse();
        }

        $validated = $request->validate([
            'media' => [
                'required',
                'array',
                'min:1',
                'max:10',
            ],

            'media.*.public_id' => [
                'required',
                'string',
                'distinct',

                Rule::exists(
                    'product_media',
                    'public_id'
                )->where(
                    function ($query) use (
                        $product
                    ): void {
                        $query
                            ->where(
                                'product_id',
                                $product->getKey()
                            )
                            ->whereNull('deleted_at');
                    }
                ),
            ],

            'media.*.sort_order' => [
                'required',
                'integer',
                'min:0',
                'max:4294967295',
                'distinct',
            ],
        ], [
            'media.required' =>
                'Product media order is required.',

            'media.array' =>
                'Product media order must be an array.',

            'media.max' =>
                'A maximum of 10 media items may be reordered.',

            'media.*.public_id.required' =>
                'Every media item must contain a public ID.',

            'media.*.public_id.exists' =>
                'One or more media items do not belong to this product.',

            'media.*.public_id.distinct' =>
                'The same media item cannot appear more than once.',

            'media.*.sort_order.required' =>
                'Every media item must contain a sort order.',

            'media.*.sort_order.distinct' =>
                'Each media item must have a different sort order.',
        ]);

        try {
            DB::transaction(
                function () use (
                    $request,
                    $product,
                    $validated
                ): void {
                    foreach ($validated['media'] as $item) {
                        ProductMedia::query()
                            ->where(
                                'product_id',
                                $product->getKey()
                            )
                            ->where(
                                'public_id',
                                $item['public_id']
                            )
                            ->update([
                                'sort_order' =>
                                    (int) $item['sort_order'],
                            ]);
                    }

                    $this->resetProductModeration(
                        product: $product,
                        userId: $request
                            ->user()
                            ?->getKey()
                    );
                }
            );

            $media = ProductMedia::query()
                ->where(
                    'product_id',
                    $product->getKey()
                )
                ->with([
                    'product:id,public_id,name,slug',
                    'variant:id,public_id,sku,name',
                    'uploadedBy:id,public_id,name,email',
                ])
                ->orderByDesc('is_primary')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            return response()->json([
                'success' => true,

                'message' =>
                    'Product media reordered successfully.',

                'data' =>
                    SellerProductMediaResource::collection(
                        $media
                    ),
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,

                'message' =>
                    'Unable to reorder the product media.',

                'data' => null,
            ], 500);
        }
    }

    /**
     * Soft-delete one product media record
     * and remove its stored file.
     */
    public function destroy(
        Request $request,
        SellerProfile $sellerProfile,
        Product $product,
        ProductMedia $media
    ): JsonResponse {
        if (! $this->canManageMedia(
            $request,
            $sellerProfile
        )) {
            return $this->forbiddenResponse();
        }

        if (! $this->productBelongsToSeller(
            $product,
            $sellerProfile
        )) {
            return $this->productNotFoundResponse();
        }

        if (! $this->mediaBelongsToProduct(
            $media,
            $product
        )) {
            return $this->mediaNotFoundResponse();
        }

        if (! $product->canBeEditedBySeller()) {
            return $this->productNotEditableResponse();
        }

        $disk = $media->disk;
        $path = $media->path;
        $wasPrimary = (bool) $media->is_primary;

        try {
            DB::transaction(
                function () use (
                    $request,
                    $product,
                    $media,
                    $wasPrimary
                ): void {
                    $media->delete();

                    if ($wasPrimary) {
                        $nextMedia = $product
                            ->media()
                            ->orderBy('sort_order')
                            ->orderBy('id')
                            ->first();

                        if ($nextMedia !== null) {
                            $nextMedia->is_primary = true;
                            $nextMedia->save();
                        }
                    }

                    $this->resetProductModeration(
                        product: $product,
                        userId: $request
                            ->user()
                            ?->getKey()
                    );
                }
            );

            if (
                is_string($disk)
                && $disk !== ''
                && is_string($path)
                && $path !== ''
            ) {
                Storage::disk($disk)->delete($path);
            }

            return response()->json([
                'success' => true,

                'message' =>
                    'Product image deleted successfully.',

                'data' => null,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'success' => false,

                'message' =>
                    'Unable to delete the product image.',

                'data' => null,
            ], 500);
        }
    }

    /**
     * Load media relationships used by the seller resource.
     */
    private function loadMediaRelations(
        ProductMedia $media
    ): void {
        $media->load([
            'product:id,public_id,name,slug',
            'variant:id,public_id,sku,name',
            'uploadedBy:id,public_id,name,email',
        ]);
    }

    /**
     * Return approved or rejected products to draft
     * after their public media changes.
     */
    private function resetProductModeration(
        Product $product,
        ?int $userId
    ): void {
        if (
            in_array(
                $product->status,
                [
                    ProductStatus::APPROVED,
                    ProductStatus::REJECTED,
                ],
                true
            )
        ) {
            $product->status =
                ProductStatus::DRAFT;

            $product->submitted_at = null;
            $product->approved_at = null;
            $product->approved_by = null;
            $product->rejected_at = null;
            $product->rejection_reason = null;
        }

        $product->updated_by = $userId;
        $product->save();
    }

    /**
     * Determine whether the authenticated user may
     * manage media for the seller profile.
     */
    private function canManageMedia(
        Request $request,
        SellerProfile $sellerProfile
    ): bool {
        $user = $request->user();

        if (
            $user === null
            || ! $sellerProfile->isApproved()
        ) {
            return false;
        }

        return $sellerProfile->members()
            ->where('user_id', $user->getKey())
            ->where('status', 'active')
            ->whereIn('role', [
                'owner',
                'manager',
            ])
            ->exists();
    }

    /**
     * Determine whether a product belongs to the seller.
     */
    private function productBelongsToSeller(
        Product $product,
        SellerProfile $sellerProfile
    ): bool {
        return (int) $product->seller_profile_id
            === (int) $sellerProfile->getKey();
    }

    /**
     * Determine whether media belongs to the product.
     */
    private function mediaBelongsToProduct(
        ProductMedia $media,
        Product $product
    ): bool {
        return (int) $media->product_id
            === (int) $product->getKey();
    }

    /**
     * Standard seller permission response.
     */
    private function forbiddenResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,

            'message' =>
                'You are not allowed to manage media for this seller business.',

            'data' => null,
        ], 403);
    }

    /**
     * Safe product-not-found response.
     */
    private function productNotFoundResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,

            'message' =>
                'The requested product was not found.',

            'data' => null,
        ], 404);
    }

    /**
     * Safe media-not-found response.
     */
    private function mediaNotFoundResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,

            'message' =>
                'The requested product image was not found.',

            'data' => null,
        ], 404);
    }

    /**
     * Response used when a product is currently locked.
     */
    private function productNotEditableResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,

            'message' =>
                'This product cannot currently be edited.',

            'data' => null,
        ], 409);
    }
}
