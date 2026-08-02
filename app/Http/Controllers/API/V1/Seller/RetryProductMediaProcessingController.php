<?php

declare(strict_types=1);

namespace App\Http\Controllers\API\V1\Seller;

use App\Http\Controllers\Controller;
use App\Http\Resources\SellerProductMediaResource;
use App\Jobs\ProcessProductMedia;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\SellerProfile;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

final class RetryProductMediaProcessingController extends Controller
{
    /**
     * A processing attempt is considered stale after this period.
     */
    private const STALE_PROCESSING_MINUTES = 15;

    /**
     * Retry product-image processing.
     */
    public function __invoke(
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

        $mediaId = DB::transaction(
            function () use (
                $media
            ): int|string {
                $lockedMedia = ProductMedia::query()
                    ->whereKey(
                        $media->getKey()
                    )
                    ->lockForUpdate()
                    ->firstOrFail();

                /*
                 * Successfully processed media should not be queued again
                 * through the retry endpoint.
                 */
                if (
                    $lockedMedia->isCompleted()
                    && $lockedMedia
                        ->hasOptimizedRendition()
                ) {
                    $this->conflict([
                        'media' => [
                            'This product image has already been processed successfully.',
                        ],
                    ]);
                }

                /*
                 * Avoid creating another active processing job unless the
                 * current processing state has become stale.
                 */
                if (
                    $lockedMedia->isProcessing()
                    && !$this->processingIsStale(
                        $lockedMedia
                    )
                ) {
                    $this->conflict([
                        'media' => [
                            'This product image is currently being processed.',
                        ],
                    ]);
                }

                if (
                    !$lockedMedia
                        ->supportsImageProcessing()
                ) {
                    throw ValidationException::withMessages([
                        'media' => [
                            'This media file is not a supported processable image.',
                        ],
                    ]);
                }

                $originalPath =
                    $lockedMedia->originalPath();

                if ($originalPath === null) {
                    throw ValidationException::withMessages([
                        'media' => [
                            'The original product image path is missing.',
                        ],
                    ]);
                }

                try {
                    $originalExists =
                        Storage::disk(
                            $lockedMedia
                                ->originalDisk()
                        )->exists(
                            $originalPath
                        );
                } catch (Throwable) {
                    throw ValidationException::withMessages([
                        'media' => [
                            'The original product image storage could not be accessed.',
                        ],
                    ]);
                }

                if (!$originalExists) {
                    throw ValidationException::withMessages([
                        'media' => [
                            'The original product image no longer exists in storage.',
                        ],
                    ]);
                }

                /*
                 * Existing successful renditions are retained until the new
                 * processing attempt completes successfully.
                 */
                $lockedMedia->markPending(
                    clearRenditions: false
                );

                return $lockedMedia->getKey();
            },
            3
        );

        /*
         * ProcessProductMedia uses afterCommit, and this dispatch occurs after
         * the transaction has successfully completed.
         */
        ProcessProductMedia::dispatch(
            $mediaId
        )->onQueue('media');

        $updatedMedia = ProductMedia::query()
            ->with([
                'variant:id,public_id,product_id,name,sku',
            ])
            ->findOrFail(
                $mediaId
            );

        return response()->json([
            'success' => true,

            'message' =>
                'Product image processing has been queued for retry.',

            'data' => (
                new SellerProductMediaResource(
                    $updatedMedia
                )
            )->resolve($request),
        ], 202);
    }

    /**
     * Determine whether a processing attempt has become stale.
     */
    private function processingIsStale(
        ProductMedia $media
    ): bool {
        $startedAt =
            $media->processing_started_at;

        if ($startedAt === null) {
            return true;
        }

        return $startedAt->lessThanOrEqualTo(
            now()->subMinutes(
                self::STALE_PROCESSING_MINUTES
            )
        );
    }

    /**
     * Ensure the product belongs to the selected seller profile.
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
     * Ensure the media belongs to the selected product.
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
     * Return a standard HTTP 409 conflict response.
     *
     * @param array<string, array<int, string>> $errors
     */
    private function conflict(
        array $errors
    ): never {
        throw new HttpResponseException(
            response()->json([
                'success' => false,

                'message' =>
                    'The product image cannot currently be queued for processing.',

                'errors' =>
                    $errors,
            ], 409)
        );
    }
}
