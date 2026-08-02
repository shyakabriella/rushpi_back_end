<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MediaType;
use App\Enums\ProductMediaProcessingStatus;
use BackedEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final class ProductMedia extends Model
{
    use HasFactory;
    use HasUlids;

    public const RENDITION_THUMBNAIL =
        'thumbnail';

    public const RENDITION_CARD =
        'card';

    public const RENDITION_DETAIL =
        'detail';

    public const RENDITION_ORIGINAL_OPTIMIZED =
        'original_optimized';

    /**
     * Rendition names supported by the media-processing service.
     *
     * @var array<int, string>
     */
    public const RENDITION_NAMES = [
        self::RENDITION_THUMBNAIL,
        self::RENDITION_CARD,
        self::RENDITION_DETAIL,
        self::RENDITION_ORIGINAL_OPTIMIZED,
    ];

    /**
     * Supported processable raster image MIME types.
     *
     * SVG is intentionally excluded because it can contain scripts and other
     * active content.
     *
     * @var array<int, string>
     */
    public const PROCESSABLE_IMAGE_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    /**
     * The database table used by this model.
     */
    protected $table = 'product_media';

    /**
     * Mass-assignable attributes.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'product_id',
        'product_variant_id',
        'media_type',

        /*
         * Existing storage columns.
         */
        'storage_disk',
        'storage_path',
        'disk',
        'path',

        'original_name',
        'mime_type',
        'size_bytes',
        'alt_text',
        'metadata',
        'is_primary',
        'sort_order',

        /*
         * Processing lifecycle.
         */
        'processing_status',
        'processing_attempts',
        'processing_error',

        /*
         * Original image information.
         */
        'original_width',
        'original_height',
        'checksum_sha256',

        /*
         * Generated renditions.
         */
        'renditions',

        /*
         * Processing timestamps.
         */
        'processing_started_at',
        'last_processing_attempt_at',
        'processed_at',
        'processing_failed_at',
    ];

    /**
     * Attributes hidden from API serialization.
     *
     * Resources should explicitly expose customer-safe storage information.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'id',
        'product_id',
        'product_variant_id',
    ];

    /**
     * Generate the ULID on public_id rather than the numeric primary key.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return [
            'public_id',
        ];
    }

    /**
     * Use the public ULID for route-model binding.
     */
    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * Model attribute casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'media_type' =>
                MediaType::class,

            'metadata' =>
                'array',

            'is_primary' =>
                'boolean',

            'sort_order' =>
                'integer',

            'size_bytes' =>
                'integer',

            'processing_status' =>
                ProductMediaProcessingStatus::class,

            'processing_attempts' =>
                'integer',

            'original_width' =>
                'integer',

            'original_height' =>
                'integer',

            'renditions' =>
                'array',

            'processing_started_at' =>
                'immutable_datetime',

            'last_processing_attempt_at' =>
                'immutable_datetime',

            'processed_at' =>
                'immutable_datetime',

            'processing_failed_at' =>
                'immutable_datetime',

            'created_at' =>
                'immutable_datetime',

            'updated_at' =>
                'immutable_datetime',
        ];
    }

    /**
     * Normalize media values before persistence.
     */
    protected static function booted(): void
    {
        static::saving(
            function (self $media): void {
                if (
                    $media->processing_status
                    === null
                ) {
                    $media->processing_status =
                        ProductMediaProcessingStatus
                            ::PENDING;
                }

                $media->processing_attempts =
                    max(
                        0,
                        (int) (
                            $media
                                ->processing_attempts
                            ?? 0
                        )
                    );

                $media->sort_order =
                    max(
                        0,
                        (int) (
                            $media->sort_order
                            ?? 0
                        )
                    );

                if ($media->alt_text !== null) {
                    $altText = trim(
                        (string) $media->alt_text
                    );

                    $media->alt_text =
                        $altText !== ''
                            ? $altText
                            : null;
                }

                if (
                    $media->processing_error
                    !== null
                ) {
                    $error = trim(
                        (string) $media
                            ->processing_error
                    );

                    $media->processing_error =
                        $error !== ''
                            ? Str::limit(
                                $error,
                                5000,
                                ''
                            )
                            : null;
                }

                if (
                    is_array($media->renditions)
                ) {
                    $media->renditions =
                        $media
                            ->normalizeRenditions(
                                $media->renditions
                            );
                }
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Product that owns this media.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(
            Product::class
        );
    }

    /**
     * Optional product variant associated with this media.
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(
            ProductVariant::class,
            'product_variant_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Query scopes
    |--------------------------------------------------------------------------
    */

    /**
     * Filter media by product.
     *
     * @param Builder<ProductMedia> $query
     */
    public function scopeForProduct(
        Builder $query,
        Product|int $product
    ): Builder {
        $productId = $product
            instanceof Product
                ? $product->getKey()
                : $product;

        return $query->where(
            'product_id',
            $productId
        );
    }

    /**
     * Filter media by variant.
     *
     * @param Builder<ProductMedia> $query
     */
    public function scopeForVariant(
        Builder $query,
        ProductVariant|int $variant
    ): Builder {
        $variantId = $variant
            instanceof ProductVariant
                ? $variant->getKey()
                : $variant;

        return $query->where(
            'product_variant_id',
            $variantId
        );
    }

    /**
     * Filter primary media.
     *
     * @param Builder<ProductMedia> $query
     */
    public function scopePrimary(
        Builder $query
    ): Builder {
        return $query->where(
            'is_primary',
            true
        );
    }

    /**
     * Filter pending media.
     *
     * @param Builder<ProductMedia> $query
     */
    public function scopePending(
        Builder $query
    ): Builder {
        return $query->where(
            'processing_status',
            ProductMediaProcessingStatus
                ::PENDING
                ->value
        );
    }

    /**
     * Filter media currently being processed.
     *
     * @param Builder<ProductMedia> $query
     */
    public function scopeProcessing(
        Builder $query
    ): Builder {
        return $query->where(
            'processing_status',
            ProductMediaProcessingStatus
                ::PROCESSING
                ->value
        );
    }

    /**
     * Filter successfully processed media.
     *
     * @param Builder<ProductMedia> $query
     */
    public function scopeCompleted(
        Builder $query
    ): Builder {
        return $query->where(
            'processing_status',
            ProductMediaProcessingStatus
                ::COMPLETED
                ->value
        );
    }

    /**
     * Filter failed media.
     *
     * @param Builder<ProductMedia> $query
     */
    public function scopeFailed(
        Builder $query
    ): Builder {
        return $query->where(
            'processing_status',
            ProductMediaProcessingStatus
                ::FAILED
                ->value
        );
    }

    /**
     * Apply the standard catalog media ordering.
     *
     * @param Builder<ProductMedia> $query
     */
    public function scopeOrdered(
        Builder $query
    ): Builder {
        return $query
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /*
    |--------------------------------------------------------------------------
    | Media information
    |--------------------------------------------------------------------------
    */

    /**
     * Return the media-type scalar value.
     */
    public function mediaTypeValue(): ?string
    {
        $type = $this->media_type;

        if ($type instanceof BackedEnum) {
            return (string) $type->value;
        }

        if ($type === null) {
            return null;
        }

        $type = trim(
            (string) $type
        );

        return $type !== ''
            ? $type
            : null;
    }

    /**
     * Determine whether the media record represents an image.
     */
    public function isImage(): bool
    {
        $mediaType =
            $this->mediaTypeValue();

        if ($mediaType === 'image') {
            return true;
        }

        return str_starts_with(
            strtolower(
                (string) $this->mime_type
            ),
            'image/'
        );
    }

    /**
     * Determine whether the original file is supported by the image processor.
     */
    public function supportsImageProcessing(): bool
    {
        if (!$this->isImage()) {
            return false;
        }

        $mimeType = strtolower(
            trim(
                (string) $this->mime_type
            )
        );

        if (
            in_array(
                $mimeType,
                self::PROCESSABLE_IMAGE_MIME_TYPES,
                true
            )
        ) {
            return $this->originalPath()
                !== null;
        }

        /*
         * Fallback for legacy records where MIME type was not stored.
         */

        $extension = strtolower(
            pathinfo(
                (string) $this->originalPath(),
                PATHINFO_EXTENSION
            )
        );

        return in_array(
            $extension,
            [
                'jpg',
                'jpeg',
                'png',
                'webp',
            ],
            true
        );
    }

    /**
     * Return the storage disk containing the original upload.
     */
    public function originalDisk(): string
    {
        $disk =
            $this->getAttribute(
                'storage_disk'
            )
            ?? $this->getAttribute(
                'disk'
            )
            ?? 'public';

        $disk = trim(
            (string) $disk
        );

        return $disk !== ''
            ? $disk
            : 'public';
    }

    /**
     * Return the original upload path.
     */
    public function originalPath(): ?string
    {
        $path =
            $this->getAttribute(
                'storage_path'
            )
            ?? $this->getAttribute(
                'path'
            );

        if ($path === null) {
            return null;
        }

        $path = trim(
            (string) $path
        );

        return $path !== ''
            ? $path
            : null;
    }

    /**
     * Return the URL of the original upload.
     */
    public function originalUrl(): ?string
    {
        return $this->storageUrl(
            $this->originalDisk(),
            $this->originalPath()
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Processing status
    |--------------------------------------------------------------------------
    */

    /**
     * Return the processing status enum.
     */
    public function processingStatus():
        ProductMediaProcessingStatus
    {
        $status =
            $this->processing_status;

        if (
            $status instanceof
            ProductMediaProcessingStatus
        ) {
            return $status;
        }

        return ProductMediaProcessingStatus
            ::tryFrom(
                (string) $status
            )
            ?? ProductMediaProcessingStatus
                ::PENDING;
    }

    /**
     * Determine whether media processing is pending.
     */
    public function isPending(): bool
    {
        return $this
            ->processingStatus()
            ->isPending();
    }

    /**
     * Determine whether media is currently processing.
     */
    public function isProcessing(): bool
    {
        return $this
            ->processingStatus()
            ->isProcessing();
    }

    /**
     * Determine whether media processing completed.
     */
    public function isCompleted(): bool
    {
        return $this
            ->processingStatus()
            ->isCompleted();
    }

    /**
     * Determine whether media processing failed.
     */
    public function isFailed(): bool
    {
        return $this
            ->processingStatus()
            ->isFailed();
    }

    /**
     * Determine whether processing can be attempted.
     */
    public function canProcess(): bool
    {
        return $this->supportsImageProcessing()
            && $this
                ->processingStatus()
                ->canRetry();
    }

    /**
     * Determine whether the record has a usable optimized image.
     */
    public function hasOptimizedRendition(): bool
    {
        foreach (
            self::RENDITION_NAMES
            as $renditionName
        ) {
            if (
                $this->hasRendition(
                    $renditionName
                )
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine whether the media can be safely exposed in catalog cards.
     */
    public function isReadyForPublicUse(): bool
    {
        return $this->isCompleted()
            && $this->hasOptimizedRendition();
    }

    /*
    |--------------------------------------------------------------------------
    | Processing lifecycle mutations
    |--------------------------------------------------------------------------
    */

    /**
     * Return media to pending processing state.
     */
    public function markPending(
        bool $clearRenditions = false
    ): self {
        $values = [
            'processing_status' =>
                ProductMediaProcessingStatus
                    ::PENDING,

            'processing_error' =>
                null,

            'processing_started_at' =>
                null,

            'processed_at' =>
                null,

            'processing_failed_at' =>
                null,
        ];

        if ($clearRenditions) {
            $values['renditions'] =
                null;

            $values['original_width'] =
                null;

            $values['original_height'] =
                null;

            $values['checksum_sha256'] =
                null;
        }

        $this->forceFill(
            $values
        )->save();

        return $this;
    }

    /**
     * Mark the processing attempt as started.
     */
    public function markProcessing(): self
    {
        $now = now();

        $this->forceFill([
            'processing_status' =>
                ProductMediaProcessingStatus
                    ::PROCESSING,

            'processing_attempts' =>
                max(
                    0,
                    (int) $this
                        ->processing_attempts
                ) + 1,

            'processing_error' =>
                null,

            'processing_started_at' =>
                $now,

            'last_processing_attempt_at' =>
                $now,

            'processed_at' =>
                null,

            'processing_failed_at' =>
                null,
        ])->save();

        return $this;
    }

    /**
     * Mark image processing as successfully completed.
     *
     * @param array<string, array<string, mixed>> $renditions
     */
    public function markCompleted(
        array $renditions,
        ?int $originalWidth = null,
        ?int $originalHeight = null,
        ?string $checksumSha256 = null
    ): self {
        $checksumSha256 =
            $checksumSha256 !== null
                ? strtolower(
                    trim($checksumSha256)
                )
                : null;

        $this->forceFill([
            'processing_status' =>
                ProductMediaProcessingStatus
                    ::COMPLETED,

            'processing_error' =>
                null,

            'original_width' =>
                $originalWidth !== null
                    ? max(1, $originalWidth)
                    : null,

            'original_height' =>
                $originalHeight !== null
                    ? max(1, $originalHeight)
                    : null,

            'checksum_sha256' =>
                $checksumSha256,

            'renditions' =>
                $this->normalizeRenditions(
                    $renditions
                ),

            'processed_at' =>
                now(),

            'processing_failed_at' =>
                null,
        ])->save();

        return $this;
    }

    /**
     * Mark media processing as failed.
     */
    public function markFailed(
        Throwable|string $error
    ): self {
        $message = $error
            instanceof Throwable
                ? $error->getMessage()
                : $error;

        $message = trim(
            (string) $message
        );

        if ($message === '') {
            $message =
                'Product media processing failed.';
        }

        $this->forceFill([
            'processing_status' =>
                ProductMediaProcessingStatus
                    ::FAILED,

            'processing_error' =>
                Str::limit(
                    $message,
                    5000,
                    ''
                ),

            'processing_failed_at' =>
                now(),

            'processed_at' =>
                null,
        ])->save();

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Rendition helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Return one rendition configuration.
     *
     * @return array<string, mixed>|null
     */
    public function rendition(
        string $name
    ): ?array {
        if (
            !in_array(
                $name,
                self::RENDITION_NAMES,
                true
            )
        ) {
            return null;
        }

        $renditions =
            $this->renditions;

        if (!is_array($renditions)) {
            return null;
        }

        $rendition =
            $renditions[$name]
            ?? null;

        return is_array($rendition)
            ? $rendition
            : null;
    }

    /**
     * Determine whether a usable rendition exists.
     */
    public function hasRendition(
        string $name
    ): bool {
        $rendition =
            $this->rendition($name);

        if ($rendition === null) {
            return false;
        }

        return isset($rendition['path'])
            && is_string(
                $rendition['path']
            )
            && trim(
                $rendition['path']
            ) !== '';
    }

    /**
     * Return the storage disk for one rendition.
     */
    public function renditionDisk(
        string $name
    ): ?string {
        $rendition =
            $this->rendition($name);

        if ($rendition === null) {
            return null;
        }

        $disk = trim(
            (string) (
                $rendition['disk']
                ?? $this->originalDisk()
            )
        );

        return $disk !== ''
            ? $disk
            : $this->originalDisk();
    }

    /**
     * Return the storage path for one rendition.
     */
    public function renditionPath(
        string $name
    ): ?string {
        $rendition =
            $this->rendition($name);

        if ($rendition === null) {
            return null;
        }

        $path = trim(
            (string) (
                $rendition['path']
                ?? ''
            )
        );

        return $path !== ''
            ? $path
            : null;
    }

    /**
     * Return the URL for one rendition.
     */
    public function renditionUrl(
        string $name
    ): ?string {
        $rendition =
            $this->rendition($name);

        if ($rendition === null) {
            return null;
        }

        $storedUrl =
            $rendition['url']
            ?? null;

        if (
            is_string($storedUrl)
            && trim($storedUrl) !== ''
        ) {
            return trim($storedUrl);
        }

        return $this->storageUrl(
            $this->renditionDisk($name),
            $this->renditionPath($name)
        );
    }

    /**
     * Select the best optimized URL for a usage context.
     */
    public function optimizedUrlFor(
        string $context
    ): ?string {
        $context = strtolower(
            trim($context)
        );

        $preference = match ($context) {
            'thumbnail' => [
                self::RENDITION_THUMBNAIL,
                self::RENDITION_CARD,
                self::RENDITION_DETAIL,
                self::RENDITION_ORIGINAL_OPTIMIZED,
            ],

            'detail' => [
                self::RENDITION_DETAIL,
                self::RENDITION_ORIGINAL_OPTIMIZED,
                self::RENDITION_CARD,
                self::RENDITION_THUMBNAIL,
            ],

            'original',
            'original_optimized' => [
                self::RENDITION_ORIGINAL_OPTIMIZED,
                self::RENDITION_DETAIL,
                self::RENDITION_CARD,
            ],

            default => [
                self::RENDITION_CARD,
                self::RENDITION_DETAIL,
                self::RENDITION_THUMBNAIL,
                self::RENDITION_ORIGINAL_OPTIMIZED,
            ],
        };

        foreach ($preference as $name) {
            $url =
                $this->renditionUrl(
                    $name
                );

            if ($url !== null) {
                return $url;
            }
        }

        return $this->originalUrl();
    }

    /**
     * Return all standard customer-facing URLs.
     *
     * @return array<string, string|null>
     */
    public function publicUrls(): array
    {
        return [
            'thumbnail' =>
                $this->optimizedUrlFor(
                    'thumbnail'
                ),

            'card' =>
                $this->optimizedUrlFor(
                    'card'
                ),

            'detail' =>
                $this->optimizedUrlFor(
                    'detail'
                ),

            'original_optimized' =>
                $this->optimizedUrlFor(
                    'original_optimized'
                ),

            'original' =>
                $this->originalUrl(),
        ];
    }

    /**
     * Return every file owned by this media record.
     *
     * This is useful when deleting or replacing the media.
     *
     * @return array<int, array{
     *     disk: string,
     *     path: string
     * }>
     */
    public function storedFiles(): array
    {
        $files = [];

        $originalPath =
            $this->originalPath();

        if ($originalPath !== null) {
            $files[] = [
                'disk' =>
                    $this->originalDisk(),

                'path' =>
                    $originalPath,
            ];
        }

        foreach (
            self::RENDITION_NAMES
            as $name
        ) {
            $path =
                $this->renditionPath(
                    $name
                );

            if ($path === null) {
                continue;
            }

            $files[] = [
                'disk' =>
                    $this->renditionDisk(
                        $name
                    )
                    ?? $this
                        ->originalDisk(),

                'path' =>
                    $path,
            ];
        }

        return collect($files)
            ->unique(
                static fn (
                    array $file
                ): string =>
                    $file['disk']
                    .'|'
                    .$file['path']
            )
            ->values()
            ->all();
    }

    /**
     * Normalize generated rendition metadata.
     *
     * @param array<string, mixed> $renditions
     *
     * @return array<string, array<string, mixed>>
     */
    public function normalizeRenditions(
        array $renditions
    ): array {
        $normalized = [];

        foreach (
            self::RENDITION_NAMES
            as $name
        ) {
            $rendition =
                $renditions[$name]
                ?? null;

            if (!is_array($rendition)) {
                continue;
            }

            $path = trim(
                (string) (
                    $rendition['path']
                    ?? ''
                )
            );

            if ($path === '') {
                continue;
            }

            $disk = trim(
                (string) (
                    $rendition['disk']
                    ?? $this->originalDisk()
                )
            );

            $url =
                $rendition['url']
                ?? null;

            $normalized[$name] = [
                'disk' =>
                    $disk !== ''
                        ? $disk
                        : $this
                            ->originalDisk(),

                'path' =>
                    $path,

                'url' =>
                    is_string($url)
                    && trim($url) !== ''
                        ? trim($url)
                        : null,

                'width' =>
                    isset($rendition['width'])
                    && is_numeric(
                        $rendition['width']
                    )
                        ? max(
                            1,
                            (int) $rendition[
                                'width'
                            ]
                        )
                        : null,

                'height' =>
                    isset($rendition['height'])
                    && is_numeric(
                        $rendition['height']
                    )
                        ? max(
                            1,
                            (int) $rendition[
                                'height'
                            ]
                        )
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
                        ? max(
                            0,
                            (int) $rendition[
                                'size_bytes'
                            ]
                        )
                        : null,

                'mime_type' =>
                    isset(
                        $rendition[
                            'mime_type'
                        ]
                    )
                        ? strtolower(
                            trim(
                                (string) $rendition[
                                    'mime_type'
                                ]
                            )
                        )
                        : null,
            ];
        }

        return $normalized;
    }

    /*
    |--------------------------------------------------------------------------
    | Storage helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Generate a storage URL without allowing storage configuration failures
     * to break API responses.
     */
    private function storageUrl(
        ?string $disk,
        ?string $path
    ): ?string {
        if (
            $disk === null
            || trim($disk) === ''
            || $path === null
            || trim($path) === ''
        ) {
            return null;
        }

        try {
            return Storage::disk(
                trim($disk)
            )->url(
                trim($path)
            );
        } catch (Throwable) {
            return null;
        }
    }
}