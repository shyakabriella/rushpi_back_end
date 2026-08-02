<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1\Seller;

use App\Enums\ProductMediaProcessingStatus;
use App\Enums\ProductStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\SellerProductMediaResource;
use App\Jobs\ProcessProductMedia;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\SellerProfile;
use BackedEnum;
use GdImage;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class ProductMediaController extends Controller
{
    /**
     * Maximum number of media records allowed per product.
     */
    private const MAX_PRODUCT_MEDIA = 20;

    /**
     * Maximum uploaded image size: 25 MB.
     */
    private const MAX_IMAGE_SIZE_KILOBYTES = 25 * 1024;

    /**
     * Maximum width or height accepted for processing.
     */
    private const MAX_IMAGE_DIMENSION = 12000;

    /**
     * Maximum total image pixel count.
     */
    private const MAX_PIXEL_COUNT = 80_000_000;

    /**
     * Supported secure raster-image MIME types.
     *
     * SVG is intentionally excluded because it may contain scripts and other
     * active content.
     *
     * @var array<int, string>
     */
    private const ALLOWED_IMAGE_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    /**
     * Supported filename extensions.
     *
     * @var array<int, string>
     */
    private const ALLOWED_IMAGE_EXTENSIONS = [
        'jpg',
        'jpeg',
        'png',
        'webp',
    ];

    /**
     * Extensions that must not appear anywhere in an uploaded filename.
     *
     * @var array<int, string>
     */
    private const DANGEROUS_EXTENSIONS = [
        'php',
        'php3',
        'php4',
        'php5',
        'php7',
        'php8',
        'phtml',
        'phar',
        'cgi',
        'pl',
        'py',
        'rb',
        'sh',
        'bash',
        'exe',
        'bat',
        'cmd',
        'com',
        'js',
        'html',
        'htm',
        'svg',
    ];

    /**
     * List product media belonging to a seller product.
     */
    public function index(
        Request $request,
        SellerProfile $sellerProfile,
        Product $product
    ): JsonResponse {
        $this->ensureProductBelongsToSeller(
            $sellerProfile,
            $product
        );

        $media = $product
            ->media()
            ->with([
                'variant:id,public_id,product_id,name,sku',
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
                SellerProductMediaResource::collection(
                    $media
                )->resolve($request),
        ]);
    }

    /**
     * Upload one secure product image and queue optimized processing.
     */
    public function store(
        Request $request,
        SellerProfile $sellerProfile,
        Product $product
    ): JsonResponse {
        $this->ensureProductBelongsToSeller(
            $sellerProfile,
            $product
        );

        $this->ensureProductCanBeEdited(
            $product
        );

        $this->normalizeMultipartBoolean(
            $request,
            'is_primary'
        );

        $validated = $request->validate([
            'image' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:'.self::MAX_IMAGE_SIZE_KILOBYTES,
            ],

            'variant_public_id' => [
                'nullable',
                'string',
                'max:64',
            ],

            'alt_text' => [
                'nullable',
                'string',
                'max:255',
            ],

            'is_primary' => [
                'nullable',
                'boolean',
            ],
        ]);

        $file = $request->file('image');

        if (!$file instanceof UploadedFile) {
            throw ValidationException::withMessages([
                'image' => [
                    'The product image is required.',
                ],
            ]);
        }

        $imageInformation =
            $this->inspectSecureImage(
                $file
            );

        $disk = $this->mediaDisk();

        $directory = sprintf(
            'product-media/%s/originals',
            (string) $product->public_id
        );

        $filename = sprintf(
            '%s.%s',
            (string) Str::ulid(),
            $this->extensionForMimeType(
                $imageInformation['mime_type']
            )
        );

        $path = Storage::disk($disk)
            ->putFileAs(
                $directory,
                $file,
                $filename
            );

        if (
            !is_string($path)
            || trim($path) === ''
        ) {
            throw ValidationException::withMessages([
                'image' => [
                    'The product image could not be stored.',
                ],
            ]);
        }

        $returnedToDraft = false;

        try {
            $media = DB::transaction(
                function () use (
                    $validated,
                    $product,
                    $file,
                    $disk,
                    $path,
                    $imageInformation,
                    &$returnedToDraft
                ): ProductMedia {
                    $lockedProduct = Product::query()
                        ->whereKey(
                            $product->getKey()
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                    $this->ensureProductCanBeEdited(
                        $lockedProduct
                    );

                    $currentMediaCount =
                        $lockedProduct
                            ->media()
                            ->count();

                    if (
                        $currentMediaCount >=
                        self::MAX_PRODUCT_MEDIA
                    ) {
                        throw ValidationException::withMessages([
                            'image' => [
                                sprintf(
                                    'A product may contain a maximum of %d images.',
                                    self::MAX_PRODUCT_MEDIA
                                ),
                            ],
                        ]);
                    }

                    $variantId = null;

                    $variantPublicId = trim(
                        (string) (
                            $validated[
                                'variant_public_id'
                            ]
                            ?? ''
                        )
                    );

                    if ($variantPublicId !== '') {
                        $variantId =
                            $lockedProduct
                                ->variants()
                                ->where(
                                    'public_id',
                                    $variantPublicId
                                )
                                ->value('id');

                        if ($variantId === null) {
                            throw ValidationException::withMessages([
                                'variant_public_id' => [
                                    'The selected product variant is invalid.',
                                ],
                            ]);
                        }
                    }

                    $requestedPrimary =
                        (bool) (
                            $validated[
                                'is_primary'
                            ]
                            ?? false
                        );

                    $hasPrimaryMedia =
                        $lockedProduct
                            ->media()
                            ->where(
                                'is_primary',
                                true
                            )
                            ->exists();

                    $isPrimary =
                        $requestedPrimary
                        || !$hasPrimaryMedia;

                    if ($isPrimary) {
                        $lockedProduct
                            ->media()
                            ->update([
                                'is_primary' =>
                                    false,
                            ]);
                    }

                    $nextSortOrder = (
                        (int) $lockedProduct
                            ->media()
                            ->max('sort_order')
                    ) + 1;

                    $payload = [
                        'product_variant_id' =>
                            $variantId,

                        'media_type' =>
                            'image',

                        'original_name' =>
                            $file
                                ->getClientOriginalName(),

                        'mime_type' =>
                            $imageInformation[
                                'mime_type'
                            ],

                        'size_bytes' =>
                            (int) $file->getSize(),

                        'alt_text' =>
                            $this->nullableTrim(
                                $validated[
                                    'alt_text'
                                ]
                                ?? null
                            ),

                        'metadata' => [
                            'client_extension' =>
                                strtolower(
                                    $file
                                        ->getClientOriginalExtension()
                                ),

                            'detected_width' =>
                                $imageInformation[
                                    'width'
                                ],

                            'detected_height' =>
                                $imageInformation[
                                    'height'
                                ],

                            'uploaded_at' =>
                                now()->toISOString(),
                        ],

                        'is_primary' =>
                            $isPrimary,

                        'sort_order' =>
                            $nextSortOrder,

                        'processing_status' =>
                            ProductMediaProcessingStatus
                                ::PENDING
                                ->value,

                        'processing_attempts' =>
                            0,

                        'processing_error' =>
                            null,

                        'renditions' =>
                            null,
                    ];

                    $payload = array_merge(
                        $payload,
                        $this->storageColumnPayload(
                            $disk,
                            $path
                        )
                    );

                    $media = $lockedProduct
                        ->media()
                        ->create($payload);

                    $returnedToDraft =
                        $this
                            ->returnProductToDraftIfModerated(
                                $lockedProduct
                            );

                    return $media;
                },
                3
            );
        } catch (Throwable $exception) {
            try {
                Storage::disk($disk)
                    ->delete($path);
            } catch (Throwable) {
                // Preserve the original exception.
            }

            throw $exception;
        }

        ProcessProductMedia::dispatch(
            $media->getKey()
        )->onQueue('media');

        $media->load([
            'variant:id,public_id,product_id,name,sku',
        ]);

        return response()->json([
            'success' => true,

            'message' => $returnedToDraft
                ? 'Product image uploaded and queued for processing. The product was returned to draft for moderation.'
                : 'Product image uploaded successfully and queued for processing.',

            'data' => (
                new SellerProductMediaResource(
                    $media
                )
            )->resolve($request),
        ], 201);
    }

    /**
     * Reorder all media belonging to a product.
     */
    public function reorder(
        Request $request,
        SellerProfile $sellerProfile,
        Product $product
    ): JsonResponse {
        $this->ensureProductBelongsToSeller(
            $sellerProfile,
            $product
        );

        $this->ensureProductCanBeEdited(
            $product
        );

        /*
         * "items" is accepted as a compatibility alias, but "media" remains
         * the documented request field.
         */
        if (
            !$request->exists('media')
            && $request->exists('items')
        ) {
            $request->merge([
                'media' =>
                    $request->input('items'),
            ]);
        }

        $validated = $request->validate([
            'media' => [
                'required',
                'array',
                'min:1',
                'max:'.self::MAX_PRODUCT_MEDIA,
            ],
        ]);

        $orderedItems =
            $this->normalizeMediaOrder(
                $validated['media']
            );

        $returnedToDraft = false;

        DB::transaction(
            function () use (
                $product,
                $orderedItems,
                &$returnedToDraft
            ): void {
                $lockedProduct = Product::query()
                    ->whereKey(
                        $product->getKey()
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->ensureProductCanBeEdited(
                    $lockedProduct
                );

                $currentMedia = ProductMedia::query()
                    ->where(
                        'product_id',
                        $lockedProduct->getKey()
                    )
                    ->lockForUpdate()
                    ->get([
                        'id',
                        'public_id',
                    ]);

                $currentPublicIds =
                    $currentMedia
                        ->pluck('public_id')
                        ->map(
                            static fn (
                                mixed $publicId
                            ): string =>
                                (string) $publicId
                        )
                        ->sort()
                        ->values()
                        ->all();

                $submittedPublicIds =
                    collect($orderedItems)
                        ->pluck('public_id')
                        ->sort()
                        ->values()
                        ->all();

                if (
                    $currentPublicIds !==
                    $submittedPublicIds
                ) {
                    throw ValidationException::withMessages([
                        'media' => [
                            'The media list must contain every product image exactly once.',
                        ],
                    ]);
                }

                $mediaByPublicId =
                    $currentMedia->keyBy(
                        static fn (
                            ProductMedia $media
                        ): string =>
                            (string) $media->public_id
                    );

                foreach (
                    $orderedItems
                    as $item
                ) {
                    $media =
                        $mediaByPublicId->get(
                            $item['public_id']
                        );

                    if (
                        !$media instanceof
                        ProductMedia
                    ) {
                        throw ValidationException::withMessages([
                            'media' => [
                                'One or more selected media records are invalid.',
                            ],
                        ]);
                    }

                    ProductMedia::query()
                        ->whereKey(
                            $media->getKey()
                        )
                        ->update([
                            'sort_order' =>
                                $item['sort_order'],
                        ]);
                }

                $returnedToDraft =
                    $this
                        ->returnProductToDraftIfModerated(
                            $lockedProduct
                        );
            },
            3
        );

        $media = $product
            ->media()
            ->with([
                'variant:id,public_id,product_id,name,sku',
            ])
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json([
            'success' => true,

            'message' => $returnedToDraft
                ? 'Product media reordered successfully and the product was returned to draft.'
                : 'Product media reordered successfully.',

            'data' =>
                SellerProductMediaResource::collection(
                    $media
                )->resolve($request),
        ]);
    }

    /**
     * Set one product image as the primary image.
     */
    public function setPrimary(
        Request $request,
        SellerProfile $sellerProfile,
        Product $product,
        ProductMedia $media
    ): JsonResponse {
        $this->ensureProductBelongsToSeller(
            $sellerProfile,
            $product
        );

        $this->ensureMediaBelongsToProduct(
            $product,
            $media
        );

        $this->ensureProductCanBeEdited(
            $product
        );

        $returnedToDraft = false;

        $updatedMedia = DB::transaction(
            function () use (
                $product,
                $media,
                &$returnedToDraft
            ): ProductMedia {
                $lockedProduct = Product::query()
                    ->whereKey(
                        $product->getKey()
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->ensureProductCanBeEdited(
                    $lockedProduct
                );

                $lockedMedia = ProductMedia::query()
                    ->whereKey(
                        $media->getKey()
                    )
                    ->where(
                        'product_id',
                        $lockedProduct->getKey()
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                $changed =
                    !(bool) $lockedMedia
                        ->is_primary;

                if ($changed) {
                    ProductMedia::query()
                        ->where(
                            'product_id',
                            $lockedProduct->getKey()
                        )
                        ->where(
                            'id',
                            '!=',
                            $lockedMedia->getKey()
                        )
                        ->update([
                            'is_primary' =>
                                false,
                        ]);

                    $lockedMedia->forceFill([
                        'is_primary' =>
                            true,
                    ])->save();

                    $returnedToDraft =
                        $this
                            ->returnProductToDraftIfModerated(
                                $lockedProduct
                            );
                }

                return $lockedMedia;
            },
            3
        );

        $updatedMedia->load([
            'variant:id,public_id,product_id,name,sku',
        ]);

        return response()->json([
            'success' => true,

            'message' => $returnedToDraft
                ? 'Primary product image updated and the product was returned to draft.'
                : 'Primary product image updated successfully.',

            'data' => (
                new SellerProductMediaResource(
                    $updatedMedia
                )
            )->resolve($request),
        ]);
    }

    /**
     * Delete one product image and all files owned by it.
     */
    public function destroy(
        Request $request,
        SellerProfile $sellerProfile,
        Product $product,
        ProductMedia $media
    ): JsonResponse {
        $this->ensureProductBelongsToSeller(
            $sellerProfile,
            $product
        );

        $this->ensureMediaBelongsToProduct(
            $product,
            $media
        );

        $this->ensureProductCanBeEdited(
            $product
        );

        $storedFiles = [];
        $returnedToDraft = false;

        DB::transaction(
            function () use (
                $product,
                $media,
                &$storedFiles,
                &$returnedToDraft
            ): void {
                $lockedProduct = Product::query()
                    ->whereKey(
                        $product->getKey()
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->ensureProductCanBeEdited(
                    $lockedProduct
                );

                $lockedMedia = ProductMedia::query()
                    ->whereKey(
                        $media->getKey()
                    )
                    ->where(
                        'product_id',
                        $lockedProduct->getKey()
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                $storedFiles =
                    $lockedMedia->storedFiles();

                $wasPrimary =
                    (bool) $lockedMedia
                        ->is_primary;

                $lockedMedia->delete();

                $remainingMedia =
                    ProductMedia::query()
                        ->where(
                            'product_id',
                            $lockedProduct->getKey()
                        )
                        ->orderByDesc(
                            'is_primary'
                        )
                        ->orderBy(
                            'sort_order'
                        )
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();

                if (
                    $wasPrimary
                    && $remainingMedia
                        ->isNotEmpty()
                ) {
                    ProductMedia::query()
                        ->where(
                            'product_id',
                            $lockedProduct->getKey()
                        )
                        ->update([
                            'is_primary' =>
                                false,
                        ]);

                    $firstMedia =
                        $remainingMedia->first();

                    if (
                        $firstMedia instanceof
                        ProductMedia
                    ) {
                        $firstMedia->forceFill([
                            'is_primary' =>
                                true,
                        ])->save();
                    }
                }

                /*
                 * Compact sort-order values after deletion.
                 */
                $remainingMedia = ProductMedia::query()
                    ->where(
                        'product_id',
                        $lockedProduct->getKey()
                    )
                    ->orderByDesc(
                        'is_primary'
                    )
                    ->orderBy(
                        'sort_order'
                    )
                    ->orderBy('id')
                    ->get();

                foreach (
                    $remainingMedia
                    as $index => $remaining
                ) {
                    if (
                        (int) $remaining
                            ->sort_order
                        === $index
                    ) {
                        continue;
                    }

                    ProductMedia::query()
                        ->whereKey(
                            $remaining->getKey()
                        )
                        ->update([
                            'sort_order' =>
                                $index,
                        ]);
                }

                $returnedToDraft =
                    $this
                        ->returnProductToDraftIfModerated(
                            $lockedProduct
                        );
            },
            3
        );

        $this->deleteStoredFiles(
            $storedFiles,
            $media
        );

        return response()->json([
            'success' => true,

            'message' => $returnedToDraft
                ? 'Product image deleted successfully and the product was returned to draft.'
                : 'Product image deleted successfully.',

            'data' => null,
        ]);
    }

    /**
     * Ensure the nested product belongs to the selected seller profile.
     */
    private function ensureProductBelongsToSeller(
        SellerProfile $sellerProfile,
        Product $product
    ): void {
        abort_unless(
            (int) $product->seller_profile_id
                === (int) $sellerProfile->getKey(),
            404,
            'The selected product does not belong to this seller profile.'
        );
    }

    /**
     * Ensure the nested media record belongs to the selected product.
     */
    private function ensureMediaBelongsToProduct(
        Product $product,
        ProductMedia $media
    ): void {
        abort_unless(
            (int) $media->product_id
                === (int) $product->getKey(),
            404,
            'The selected media does not belong to this product.'
        );
    }

    /**
     * Prevent media changes while the product lifecycle blocks editing.
     */
    private function ensureProductCanBeEdited(
        Product $product
    ): void {
        $status =
            $this->productStatus(
                $product
            );

        if (
            in_array(
                $status,
                [
                    ProductStatus::DRAFT,
                    ProductStatus::APPROVED,
                    ProductStatus::REJECTED,
                ],
                true
            )
        ) {
            return;
        }

        throw new HttpResponseException(
            response()->json([
                'success' => false,

                'message' =>
                    'Product media cannot be changed while the product has its current status.',

                'errors' => [
                    'status' => [
                        sprintf(
                            'The current product status is %s.',
                            $status->value
                        ),
                    ],
                ],
            ], 409)
        );
    }

    /**
     * Read product status regardless of enum casting.
     */
    private function productStatus(
        Product $product
    ): ProductStatus {
        $status = $product->status;

        if ($status instanceof ProductStatus) {
            return $status;
        }

        if ($status instanceof BackedEnum) {
            $status = $status->value;
        }

        return ProductStatus::from(
            (string) $status
        );
    }

    /**
     * Return approved or rejected products to draft after media changes.
     */
    private function returnProductToDraftIfModerated(
        Product $product
    ): bool {
        $status =
            $this->productStatus(
                $product
            );

        if (
            !in_array(
                $status,
                [
                    ProductStatus::APPROVED,
                    ProductStatus::REJECTED,
                ],
                true
            )
        ) {
            return false;
        }

        $this->setExistingProductAttributes(
            $product,
            [
                'status' =>
                    ProductStatus::DRAFT
                        ->value,

                'submitted_at' =>
                    null,

                'approved_at' =>
                    null,

                'approved_by' =>
                    null,

                'rejected_at' =>
                    null,

                'rejected_by' =>
                    null,

                'rejection_reason' =>
                    null,

                'suspended_at' =>
                    null,

                'suspended_by' =>
                    null,

                'suspension_reason' =>
                    null,

                'archived_at' =>
                    null,
            ]
        );

        $product->save();

        return true;
    }

    /**
     * Set only product attributes that exist in the current schema.
     *
     * @param array<string, mixed> $values
     */
    private function setExistingProductAttributes(
        Product $product,
        array $values
    ): void {
        $attributes =
            $product->getAttributes();

        foreach ($values as $key => $value) {
            if (
                $key === 'status'
                || array_key_exists(
                    $key,
                    $attributes
                )
            ) {
                $product->setAttribute(
                    $key,
                    $value
                );
            }
        }
    }

    /**
     * Inspect the uploaded image using its real contents.
     *
     * @return array{
     *     mime_type: string,
     *     width: int,
     *     height: int
     * }
     */
    private function inspectSecureImage(
        UploadedFile $file
    ): array {
        if (!$file->isValid()) {
            $this->failImageValidation(
                'The uploaded product image is invalid.'
            );
        }

        $originalName = trim(
            $file->getClientOriginalName()
        );

        if (
            $originalName === ''
            || str_contains(
                $originalName,
                "\0"
            )
        ) {
            $this->failImageValidation(
                'The product image filename is invalid.'
            );
        }

        $filenameParts = array_map(
            static fn (
                string $part
            ): string => strtolower(
                trim($part)
            ),
            explode(
                '.',
                $originalName
            )
        );

        $extension = strtolower(
            $file
                ->getClientOriginalExtension()
        );

        if (
            !in_array(
                $extension,
                self::ALLOWED_IMAGE_EXTENSIONS,
                true
            )
        ) {
            $this->failImageValidation(
                'The product image must use a JPG, JPEG, PNG or WebP extension.'
            );
        }

        foreach ($filenameParts as $part) {
            if (
                in_array(
                    $part,
                    self::DANGEROUS_EXTENSIONS,
                    true
                )
            ) {
                $this->failImageValidation(
                    'The product image filename contains an unsafe extension.'
                );
            }
        }

        $realPath = $file->getRealPath();

        if (
            !is_string($realPath)
            || !is_file($realPath)
        ) {
            $this->failImageValidation(
                'The uploaded image could not be inspected.'
            );
        }

        $fileSize = filesize($realPath);

        if (
            $fileSize === false
            || $fileSize < 1
        ) {
            $this->failImageValidation(
                'The uploaded product image is empty.'
            );
        }

        if (
            $fileSize >
            self::MAX_IMAGE_SIZE_KILOBYTES
                * 1024
        ) {
            $this->failImageValidation(
                'The product image exceeds the maximum size of 25 MB.'
            );
        }

        if (!function_exists('finfo_open')) {
            $this->failImageValidation(
                'Secure file-type inspection is unavailable on the server.'
            );
        }

        $fileInfo = finfo_open(
            FILEINFO_MIME_TYPE
        );

        if ($fileInfo === false) {
            $this->failImageValidation(
                'The product image MIME type could not be inspected.'
            );
        }

        try {
            $detectedMimeType =
                finfo_file(
                    $fileInfo,
                    $realPath
                );
        } finally {
            finfo_close($fileInfo);
        }

        if (
            !is_string(
                $detectedMimeType
            )
        ) {
            $this->failImageValidation(
                'The product image MIME type could not be determined.'
            );
        }

        $detectedMimeType =
            strtolower(
                trim(
                    $detectedMimeType
                )
            );

        if (
            !in_array(
                $detectedMimeType,
                self::ALLOWED_IMAGE_MIME_TYPES,
                true
            )
        ) {
            $this->failImageValidation(
                'Only genuine JPEG, PNG and WebP images are accepted.'
            );
        }

        $imageInformation =
            @getimagesize($realPath);

        if (
            $imageInformation === false
            || !isset(
                $imageInformation[0],
                $imageInformation[1],
                $imageInformation['mime']
            )
        ) {
            $this->failImageValidation(
                'The uploaded file is not a valid decodable image.'
            );
        }

        $width =
            (int) $imageInformation[0];

        $height =
            (int) $imageInformation[1];

        $imageMimeType =
            strtolower(
                trim(
                    (string) $imageInformation[
                        'mime'
                    ]
                )
            );

        if (
            $detectedMimeType !==
            $imageMimeType
        ) {
            $this->failImageValidation(
                'The image MIME type does not match its contents.'
            );
        }

        if (
            $width < 1
            || $height < 1
        ) {
            $this->failImageValidation(
                'The uploaded image dimensions are invalid.'
            );
        }

        if (
            $width >
                self::MAX_IMAGE_DIMENSION
            || $height >
                self::MAX_IMAGE_DIMENSION
        ) {
            $this->failImageValidation(
                sprintf(
                    'Image dimensions cannot exceed %d pixels on either side.',
                    self::MAX_IMAGE_DIMENSION
                )
            );
        }

        if (
            $width * $height >
            self::MAX_PIXEL_COUNT
        ) {
            $this->failImageValidation(
                sprintf(
                    'The uploaded image exceeds the maximum pixel count of %d.',
                    self::MAX_PIXEL_COUNT
                )
            );
        }

        if (
            !extension_loaded('gd')
            || !function_exists(
                'imagecreatefromstring'
            )
        ) {
            $this->failImageValidation(
                'The server image-processing extension is unavailable.'
            );
        }

        $binary =
            file_get_contents(
                $realPath
            );

        if (
            $binary === false
            || $binary === ''
        ) {
            $this->failImageValidation(
                'The uploaded image could not be read.'
            );
        }

        $decoded =
            @imagecreatefromstring(
                $binary
            );

        if (!$decoded instanceof GdImage) {
            $this->failImageValidation(
                'The uploaded product image could not be decoded securely.'
            );
        }

        imagedestroy($decoded);

        return [
            'mime_type' =>
                $detectedMimeType,

            'width' =>
                $width,

            'height' =>
                $height,
        ];
    }

    /**
     * Throw a standard image-validation exception.
     */
    private function failImageValidation(
        string $message
    ): never {
        throw ValidationException::withMessages([
            'image' => [
                $message,
            ],
        ]);
    }

    /**
     * Return a safe output extension for a verified MIME type.
     */
    private function extensionForMimeType(
        string $mimeType
    ): string {
        return match ($mimeType) {
            'image/jpeg' =>
                'jpg',

            'image/png' =>
                'png',

            'image/webp' =>
                'webp',

            default =>
                throw new RuntimeException(
                    'Unsupported product image MIME type.'
                ),
        };
    }

    /**
     * Return the configured product-media storage disk.
     */
    private function mediaDisk(): string
    {
        $disk = config(
            'filesystems.product_media_disk',
            'public'
        );

        $disk = is_string($disk)
            ? trim($disk)
            : 'public';

        if ($disk === '') {
            $disk = 'public';
        }

        if (
            config(
                "filesystems.disks.{$disk}"
            ) === null
        ) {
            throw new RuntimeException(
                sprintf(
                    'The product media storage disk "%s" is not configured.',
                    $disk
                )
            );
        }

        return $disk;
    }

    /**
     * Support either storage_disk/storage_path or legacy disk/path columns.
     *
     * @return array<string, string>
     */
    private function storageColumnPayload(
        string $disk,
        string $path
    ): array {
        $payload = [];

        $hasStorageColumns =
            Schema::hasColumn(
                'product_media',
                'storage_disk'
            )
            && Schema::hasColumn(
                'product_media',
                'storage_path'
            );

        $hasLegacyColumns =
            Schema::hasColumn(
                'product_media',
                'disk'
            )
            && Schema::hasColumn(
                'product_media',
                'path'
            );

        if ($hasStorageColumns) {
            $payload[
                'storage_disk'
            ] = $disk;

            $payload[
                'storage_path'
            ] = $path;
        }

        if ($hasLegacyColumns) {
            $payload['disk'] =
                $disk;

            $payload['path'] =
                $path;
        }

        if ($payload === []) {
            throw new RuntimeException(
                'The product_media table does not contain supported storage columns.'
            );
        }

        return $payload;
    }

    /**
     * Normalize multipart boolean values before validation.
     */
    private function normalizeMultipartBoolean(
        Request $request,
        string $field
    ): void {
        if (!$request->exists($field)) {
            return;
        }

        $value = $request->input(
            $field
        );

        if (is_bool($value)) {
            return;
        }

        $normalized = filter_var(
            $value,
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        );

        if ($normalized !== null) {
            $request->merge([
                $field =>
                    $normalized,
            ]);
        }
    }

    /**
     * Normalize media-order request values.
     *
     * Supported formats:
     *
     * {
     *   "media": [
     *     "MEDIA_PUBLIC_ID_1",
     *     "MEDIA_PUBLIC_ID_2"
     *   ]
     * }
     *
     * {
     *   "media": [
     *     {
     *       "public_id": "MEDIA_PUBLIC_ID_1",
     *       "sort_order": 0
     *     }
     *   ]
     * }
     *
     * @param array<int, mixed> $values
     *
     * @return array<int, array{
     *     public_id: string,
     *     sort_order: int
     * }>
     */
    private function normalizeMediaOrder(
        array $values
    ): array {
        $normalized = [];

        foreach (
            array_values($values)
            as $index => $value
        ) {
            $publicId = null;
            $requestedOrder = $index;

            if (is_string($value)) {
                $publicId =
                    trim($value);
            } elseif (is_array($value)) {
                $publicId = trim(
                    (string) (
                        $value['public_id']
                        ?? $value[
                            'media_public_id'
                        ]
                        ?? $value['id']
                        ?? ''
                    )
                );

                if (
                    array_key_exists(
                        'sort_order',
                        $value
                    )
                ) {
                    if (
                        !is_numeric(
                            $value[
                                'sort_order'
                            ]
                        )
                        || (int) $value[
                            'sort_order'
                        ] < 0
                    ) {
                        throw ValidationException::withMessages([
                            "media.{$index}.sort_order" => [
                                'The sort order must be a non-negative integer.',
                            ],
                        ]);
                    }

                    $requestedOrder =
                        (int) $value[
                            'sort_order'
                        ];
                }
            } else {
                throw ValidationException::withMessages([
                    "media.{$index}" => [
                        'Each media entry must be a public identifier or an object.',
                    ],
                ]);
            }

            if (
                $publicId === null
                || $publicId === ''
            ) {
                throw ValidationException::withMessages([
                    "media.{$index}.public_id" => [
                        'Each media entry must contain a public identifier.',
                    ],
                ]);
            }

            $normalized[] = [
                'public_id' =>
                    $publicId,

                'requested_order' =>
                    $requestedOrder,

                'original_index' =>
                    $index,
            ];
        }

        $publicIds = array_column(
            $normalized,
            'public_id'
        );

        if (
            count($publicIds)
            !== count(
                array_unique($publicIds)
            )
        ) {
            throw ValidationException::withMessages([
                'media' => [
                    'The media list contains duplicate public identifiers.',
                ],
            ]);
        }

        usort(
            $normalized,
            static function (
                array $left,
                array $right
            ): int {
                $orderComparison =
                    $left['requested_order']
                    <=>
                    $right['requested_order'];

                if ($orderComparison !== 0) {
                    return $orderComparison;
                }

                return $left['original_index']
                    <=>
                    $right['original_index'];
            }
        );

        return array_map(
            static fn (
                array $item,
                int $index
            ): array => [
                'public_id' =>
                    $item['public_id'],

                'sort_order' =>
                    $index,
            ],
            $normalized,
            array_keys($normalized)
        );
    }

    /**
     * Delete original and generated rendition files.
     *
     * @param array<int, array{
     *     disk: string,
     *     path: string
     * }> $files
     */
    private function deleteStoredFiles(
        array $files,
        ProductMedia $media
    ): void {
        $groupedFiles = [];

        foreach ($files as $file) {
            $disk = trim(
                (string) (
                    $file['disk']
                    ?? ''
                )
            );

            $path = trim(
                (string) (
                    $file['path']
                    ?? ''
                )
            );

            if (
                $disk === ''
                || $path === ''
            ) {
                continue;
            }

            $groupedFiles[$disk][] =
                $path;
        }

        foreach (
            $groupedFiles
            as $disk => $paths
        ) {
            try {
                Storage::disk($disk)
                    ->delete(
                        array_values(
                            array_unique(
                                $paths
                            )
                        )
                    );
            } catch (Throwable $exception) {
                Log::warning(
                    'Unable to delete product media files.',
                    [
                        'product_media_public_id' =>
                            $media->public_id,

                        'disk' =>
                            $disk,

                        'paths' =>
                            $paths,

                        'message' =>
                            $exception
                                ->getMessage(),
                    ]
                );
            }
        }
    }

    /**
     * Trim optional text.
     */
    private function nullableTrim(
        mixed $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value = trim(
            (string) $value
        );

        return $value !== ''
            ? $value
            : null;
    }
}