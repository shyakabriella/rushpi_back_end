<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\ProductMediaProcessingStatus;
use App\Models\ProductMedia;
use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductMedia
 */
final class SellerProductMediaResource extends JsonResource
{
    /**
     * Transform product media into a seller-facing API response.
     *
     * @return array<string, mixed>
     */
    public function toArray(
        Request $request
    ): array {
        $processingStatus =
            $this->processingStatus();

        return [
            /*
            |--------------------------------------------------------------------------
            | Identity
            |--------------------------------------------------------------------------
            */

            'public_id' =>
                (string) $this->public_id,

            'media_type' =>
                $this->enumValue(
                    $this->media_type
                ),

            /*
            |--------------------------------------------------------------------------
            | Variant assignment
            |--------------------------------------------------------------------------
            */

            'variant' =>
                $this->whenLoaded(
                    'variant',
                    function (): ?array {
                        $variant =
                            $this->variant;

                        if (!$variant instanceof Model) {
                            return null;
                        }

                        return [
                            'public_id' =>
                                (string) $variant
                                    ->getAttribute(
                                        'public_id'
                                    ),

                            'name' =>
                                $variant->getAttribute(
                                    'name'
                                ),

                            'sku' =>
                                $variant->getAttribute(
                                    'sku'
                                ),
                        ];
                    }
                ),

            /*
            |--------------------------------------------------------------------------
            | Uploaded file
            |--------------------------------------------------------------------------
            */

            'original_name' =>
                $this->original_name,

            'mime_type' =>
                $this->mime_type,

            'size_bytes' =>
                $this->size_bytes !== null
                    ? (int) $this->size_bytes
                    : null,

            'alt_text' =>
                $this->alt_text,

            'metadata' =>
                is_array($this->metadata)
                    ? $this->metadata
                    : [],

            /*
            |--------------------------------------------------------------------------
            | Display configuration
            |--------------------------------------------------------------------------
            */

            'is_primary' =>
                (bool) $this->is_primary,

            'sort_order' =>
                (int) $this->sort_order,

            /*
            |--------------------------------------------------------------------------
            | Processing lifecycle
            |--------------------------------------------------------------------------
            */

            'processing' => [
                'status' =>
                    $processingStatus->value,

                'label' =>
                    $processingStatus->label(),

                'attempts' =>
                    (int) (
                        $this->processing_attempts
                        ?? 0
                    ),

                'error' =>
                    $this->processing_error,

                'is_pending' =>
                    $processingStatus
                        ->isPending(),

                'is_processing' =>
                    $processingStatus
                        ->isProcessing(),

                'is_completed' =>
                    $processingStatus
                        ->isCompleted(),

                'is_failed' =>
                    $processingStatus
                        ->isFailed(),

                'is_finished' =>
                    $processingStatus
                        ->isFinished(),

                'can_retry' =>
                    $processingStatus
                        ->canRetry()
                    && $this
                        ->supportsImageProcessing(),

                'is_ready_for_public_use' =>
                    $this
                        ->isReadyForPublicUse(),

                'started_at' =>
                    $this->dateValue(
                        $this
                            ->processing_started_at
                    ),

                'last_attempt_at' =>
                    $this->dateValue(
                        $this
                            ->last_processing_attempt_at
                    ),

                'processed_at' =>
                    $this->dateValue(
                        $this->processed_at
                    ),

                'failed_at' =>
                    $this->dateValue(
                        $this
                            ->processing_failed_at
                    ),
            ],

            /*
            |--------------------------------------------------------------------------
            | Original image information
            |--------------------------------------------------------------------------
            */

            'original' => [
                'disk' =>
                    $this->originalDisk(),

                'path' =>
                    $this->originalPath(),

                'url' =>
                    $this->originalUrl(),

                'width' =>
                    $this->original_width !== null
                        ? (int) $this
                            ->original_width
                        : null,

                'height' =>
                    $this->original_height !== null
                        ? (int) $this
                            ->original_height
                        : null,

                'checksum_sha256' =>
                    $this->checksum_sha256,
            ],

            /*
            |--------------------------------------------------------------------------
            | Generated renditions
            |--------------------------------------------------------------------------
            */

            'renditions' => [
                'thumbnail' =>
                    $this->renditionData(
                        ProductMedia
                            ::RENDITION_THUMBNAIL
                    ),

                'card' =>
                    $this->renditionData(
                        ProductMedia
                            ::RENDITION_CARD
                    ),

                'detail' =>
                    $this->renditionData(
                        ProductMedia
                            ::RENDITION_DETAIL
                    ),

                'original_optimized' =>
                    $this->renditionData(
                        ProductMedia
                            ::RENDITION_ORIGINAL_OPTIMIZED
                    ),
            ],

            /*
            |--------------------------------------------------------------------------
            | Optimized URLs
            |--------------------------------------------------------------------------
            */

            'urls' =>
                $this->publicUrls(),

            'preferred_url' =>
                $this->optimizedUrlFor(
                    'card'
                ),

            /*
            |--------------------------------------------------------------------------
            | Capability information
            |--------------------------------------------------------------------------
            */

            'capabilities' => [
                'supports_processing' =>
                    $this
                        ->supportsImageProcessing(),

                'has_optimized_rendition' =>
                    $this
                        ->hasOptimizedRendition(),

                'can_be_primary' =>
                    $this->isImage(),

                'can_retry_processing' =>
                    $processingStatus
                        ->canRetry()
                    && $this
                        ->supportsImageProcessing(),
            ],

            /*
            |--------------------------------------------------------------------------
            | Timestamps
            |--------------------------------------------------------------------------
            */

            'created_at' =>
                $this->dateValue(
                    $this->created_at
                ),

            'updated_at' =>
                $this->dateValue(
                    $this->updated_at
                ),
        ];
    }

    /**
     * Transform one generated rendition.
     *
     * @return array<string, mixed>|null
     */
    private function renditionData(
        string $name
    ): ?array {
        $rendition =
            $this->rendition($name);

        if ($rendition === null) {
            return null;
        }

        return [
            'name' =>
                $name,

            'disk' =>
                $this->renditionDisk(
                    $name
                ),

            'path' =>
                $this->renditionPath(
                    $name
                ),

            'url' =>
                $this->renditionUrl(
                    $name
                ),

            'width' =>
                isset(
                    $rendition['width']
                )
                && is_numeric(
                    $rendition['width']
                )
                    ? (int) $rendition[
                        'width'
                    ]
                    : null,

            'height' =>
                isset(
                    $rendition['height']
                )
                && is_numeric(
                    $rendition['height']
                )
                    ? (int) $rendition[
                        'height'
                    ]
                    : null,

            'size_bytes' =>
                isset(
                    $rendition[
                        'size_bytes'
                    ]
                )
                && is_numeric(
                    $rendition[
                        'size_bytes'
                    ]
                )
                    ? (int) $rendition[
                        'size_bytes'
                    ]
                    : null,

            'mime_type' =>
                isset(
                    $rendition[
                        'mime_type'
                    ]
                )
                    ? (string) $rendition[
                        'mime_type'
                    ]
                    : null,
        ];
    }

    /**
     * Convert an enum or scalar into an API string.
     */
    private function enumValue(
        mixed $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        $value = trim(
            (string) $value
        );

        return $value !== ''
            ? $value
            : null;
    }

    /**
     * Convert a date value into an ISO-8601 string.
     */
    private function dateValue(
        mixed $value
    ): ?string {
        if ($value instanceof CarbonInterface) {
            return $value->toISOString();
        }

        if (
            is_string($value)
            && trim($value) !== ''
        ) {
            return trim($value);
        }

        return null;
    }
}