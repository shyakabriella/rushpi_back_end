<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\ProductMedia;
use GdImage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class ProductMediaProcessor
{
    /**
     * Maximum accepted original upload size.
     */
    private const MAX_FILE_SIZE_BYTES =
        25 * 1024 * 1024;

    /**
     * Maximum accepted original image width or height.
     */
    private const MAX_IMAGE_DIMENSION =
        12000;

    /**
     * Maximum total pixel count used to reduce decompression-bomb risk.
     */
    private const MAX_PIXEL_COUNT =
        80_000_000;

    /**
     * Generated rendition configuration.
     *
     * cover:
     *     Produces an exact square output using center cropping.
     *
     * fit:
     *     Preserves the complete image and does not upscale it.
     *
     * @var array<string, array{
     *     width: int,
     *     height: int,
     *     mode: string,
     *     quality: int
     * }>
     */
    private const RENDITION_CONFIGURATIONS = [
        ProductMedia::RENDITION_THUMBNAIL => [
            'width' => 200,
            'height' => 200,
            'mode' => 'cover',
            'quality' => 78,
        ],

        ProductMedia::RENDITION_CARD => [
            'width' => 600,
            'height' => 600,
            'mode' => 'cover',
            'quality' => 82,
        ],

        ProductMedia::RENDITION_DETAIL => [
            'width' => 1200,
            'height' => 1200,
            'mode' => 'fit',
            'quality' => 86,
        ],

        ProductMedia::RENDITION_ORIGINAL_OPTIMIZED => [
            'width' => 2000,
            'height' => 2000,
            'mode' => 'fit',
            'quality' => 88,
        ],
    ];

    /**
     * Process one product image and generate optimized renditions.
     *
     * This method does not update the ProductMedia database record. The queue
     * job will call markProcessing(), markCompleted() or markFailed().
     *
     * @return array{
     *     renditions: array<string, array<string, mixed>>,
     *     original_width: int,
     *     original_height: int,
     *     checksum_sha256: string,
     *     detected_mime_type: string
     * }
     */
    public function process(
        ProductMedia $media
    ): array {
        $this->ensureGdSupport();

        if (!$media->isImage()) {
            throw new RuntimeException(
                'Only product images can be processed.'
            );
        }

        $diskName =
            $media->originalDisk();

        $originalPath =
            $media->originalPath();

        if ($originalPath === null) {
            throw new RuntimeException(
                'The original product image path is missing.'
            );
        }

        $disk = Storage::disk(
            $diskName
        );

        if (!$disk->exists($originalPath)) {
            throw new RuntimeException(
                'The original product image does not exist in storage.'
            );
        }

        $binary = $disk->get(
            $originalPath
        );

        if ($binary === '') {
            throw new RuntimeException(
                'The original product image is empty.'
            );
        }

        $fileSize = strlen($binary);

        if (
            $fileSize >
            self::MAX_FILE_SIZE_BYTES
        ) {
            throw new RuntimeException(
                sprintf(
                    'The original image exceeds the maximum processing size of %d MB.',
                    (int) (
                        self::MAX_FILE_SIZE_BYTES
                        / 1024
                        / 1024
                    )
                )
            );
        }

        $detectedMimeType =
            $this->detectMimeType(
                $binary
            );

        if (
            !in_array(
                $detectedMimeType,
                ProductMedia
                    ::PROCESSABLE_IMAGE_MIME_TYPES,
                true
            )
        ) {
            throw new RuntimeException(
                sprintf(
                    'Unsupported or unsafe image MIME type: %s.',
                    $detectedMimeType
                )
            );
        }

        $imageInformation =
            $this->readImageInformation(
                $binary
            );

        $originalWidth =
            $imageInformation['width'];

        $originalHeight =
            $imageInformation['height'];

        $informationMimeType =
            $imageInformation['mime_type'];

        if (
            $informationMimeType !==
            $detectedMimeType
        ) {
            throw new RuntimeException(
                'The uploaded file MIME type does not match its image contents.'
            );
        }

        $this->validateDimensions(
            width: $originalWidth,
            height: $originalHeight
        );

        $checksum = hash(
            'sha256',
            $binary
        );

        $sourceImage =
            $this->decodeImage(
                $binary
            );

        $renditions = [];
        $storedPaths = [];

        try {
            foreach (
                self::RENDITION_CONFIGURATIONS
                as $name => $configuration
            ) {
                $renderedImage =
                    $this->renderRendition(
                        source:
                            $sourceImage,

                        sourceWidth:
                            $originalWidth,

                        sourceHeight:
                            $originalHeight,

                        targetWidth:
                            $configuration[
                                'width'
                            ],

                        targetHeight:
                            $configuration[
                                'height'
                            ],

                        mode:
                            $configuration[
                                'mode'
                            ]
                    );

                try {
                    $encoded =
                        $this->encodeWebp(
                            image:
                                $renderedImage,

                            quality:
                                $configuration[
                                    'quality'
                                ]
                        );

                    $path =
                        $this->renditionPath(
                            media:
                                $media,

                            checksum:
                                $checksum,

                            renditionName:
                                $name
                        );

                    $stored =
                        $disk->put(
                            $path,
                            $encoded
                        );

                    if (!$stored) {
                        throw new RuntimeException(
                            sprintf(
                                'Unable to store the %s product image rendition.',
                                $name
                            )
                        );
                    }

                    $storedPaths[] =
                        $path;

                    $renditions[$name] = [
                        'disk' =>
                            $diskName,

                        'path' =>
                            $path,

                        /*
                         * URLs are generated dynamically by ProductMedia.
                         */
                        'url' =>
                            null,

                        'width' =>
                            imagesx(
                                $renderedImage
                            ),

                        'height' =>
                            imagesy(
                                $renderedImage
                            ),

                        'size_bytes' =>
                            strlen($encoded),

                        'mime_type' =>
                            'image/webp',
                    ];
                } finally {
                    imagedestroy(
                        $renderedImage
                    );
                }
            }
        } catch (Throwable $exception) {
            $this->deletePaths(
                disk: $disk,
                paths: $storedPaths
            );

            throw $exception;
        } finally {
            imagedestroy(
                $sourceImage
            );
        }

        return [
            'renditions' =>
                $renditions,

            'original_width' =>
                $originalWidth,

            'original_height' =>
                $originalHeight,

            'checksum_sha256' =>
                $checksum,

            'detected_mime_type' =>
                $detectedMimeType,
        ];
    }

    /**
     * Delete rendition files currently referenced by a media record.
     */
    public function deleteRenditions(
        ProductMedia $media
    ): void {
        $groupedFiles = [];

        foreach (
            ProductMedia::RENDITION_NAMES
            as $renditionName
        ) {
            $path =
                $media->renditionPath(
                    $renditionName
                );

            if ($path === null) {
                continue;
            }

            $disk =
                $media->renditionDisk(
                    $renditionName
                )
                ?? $media
                    ->originalDisk();

            $groupedFiles[$disk][] =
                $path;
        }

        foreach (
            $groupedFiles
            as $diskName => $paths
        ) {
            try {
                Storage::disk(
                    $diskName
                )->delete(
                    array_values(
                        array_unique(
                            $paths
                        )
                    )
                );
            } catch (Throwable) {
                /*
                 * Storage cleanup should not cause product deletion or API
                 * responses to fail. Orphan cleanup can retry asynchronously.
                 */
            }
        }
    }

    /**
     * Verify that GD and WebP encoding are available.
     */
    private function ensureGdSupport(): void
    {
        if (!extension_loaded('gd')) {
            throw new RuntimeException(
                'The PHP GD extension is required for product image processing.'
            );
        }

        if (
            !function_exists(
                'imagecreatefromstring'
            )
            || !function_exists(
                'imagewebp'
            )
        ) {
            throw new RuntimeException(
                'The installed PHP GD extension does not support the required image functions.'
            );
        }

        if (
            defined('IMG_WEBP')
            && (
                imagetypes()
                & IMG_WEBP
            ) !== IMG_WEBP
        ) {
            throw new RuntimeException(
                'The installed PHP GD extension does not support WebP encoding.'
            );
        }
    }

    /**
     * Detect the real MIME type using the uploaded file contents.
     */
    private function detectMimeType(
        string $binary
    ): string {
        if (!function_exists('finfo_open')) {
            throw new RuntimeException(
                'The PHP Fileinfo extension is required for secure image validation.'
            );
        }

        $fileInfo = finfo_open(
            FILEINFO_MIME_TYPE
        );

        if ($fileInfo === false) {
            throw new RuntimeException(
                'Unable to initialize MIME-type detection.'
            );
        }

        try {
            $mimeType = finfo_buffer(
                $fileInfo,
                $binary
            );
        } finally {
            finfo_close(
                $fileInfo
            );
        }

        if (
            !is_string($mimeType)
            || trim($mimeType) === ''
        ) {
            throw new RuntimeException(
                'Unable to determine the uploaded image MIME type.'
            );
        }

        return strtolower(
            trim($mimeType)
        );
    }

    /**
     * Read image dimensions and MIME information without trusting filenames.
     *
     * @return array{
     *     width: int,
     *     height: int,
     *     mime_type: string
     * }
     */
    private function readImageInformation(
        string $binary
    ): array {
        $information =
            @getimagesizefromstring(
                $binary
            );

        if (
            $information === false
            || !isset(
                $information[0],
                $information[1],
                $information['mime']
            )
        ) {
            throw new RuntimeException(
                'The uploaded file is not a valid decodable image.'
            );
        }

        return [
            'width' =>
                (int) $information[0],

            'height' =>
                (int) $information[1],

            'mime_type' =>
                strtolower(
                    trim(
                        (string) $information[
                            'mime'
                        ]
                    )
                ),
        ];
    }

    /**
     * Validate image dimensions and total pixel count.
     */
    private function validateDimensions(
        int $width,
        int $height
    ): void {
        if (
            $width < 1
            || $height < 1
        ) {
            throw new RuntimeException(
                'The uploaded image dimensions are invalid.'
            );
        }

        if (
            $width >
                self::MAX_IMAGE_DIMENSION
            || $height >
                self::MAX_IMAGE_DIMENSION
        ) {
            throw new RuntimeException(
                sprintf(
                    'Image dimensions cannot exceed %d pixels on either side.',
                    self::MAX_IMAGE_DIMENSION
                )
            );
        }

        $pixelCount =
            $width * $height;

        if (
            $pixelCount >
            self::MAX_PIXEL_COUNT
        ) {
            throw new RuntimeException(
                sprintf(
                    'The uploaded image contains too many pixels. Maximum allowed: %d.',
                    self::MAX_PIXEL_COUNT
                )
            );
        }
    }

    /**
     * Decode the validated image into a GD image resource.
     */
    private function decodeImage(
        string $binary
    ): GdImage {
        $image =
            @imagecreatefromstring(
                $binary
            );

        if (!$image instanceof GdImage) {
            throw new RuntimeException(
                'PHP GD could not decode the uploaded product image.'
            );
        }

        imagealphablending(
            $image,
            true
        );

        imagesavealpha(
            $image,
            true
        );

        if (
            function_exists(
                'imagesetinterpolation'
            )
        ) {
            imagesetinterpolation(
                $image,
                IMG_BICUBIC_FIXED
            );
        }

        return $image;
    }

    /**
     * Create one resized rendition.
     */
    private function renderRendition(
        GdImage $source,
        int $sourceWidth,
        int $sourceHeight,
        int $targetWidth,
        int $targetHeight,
        string $mode
    ): GdImage {
        return match ($mode) {
            'cover' =>
                $this->renderCover(
                    source:
                        $source,

                    sourceWidth:
                        $sourceWidth,

                    sourceHeight:
                        $sourceHeight,

                    targetWidth:
                        $targetWidth,

                    targetHeight:
                        $targetHeight
                ),

            'fit' =>
                $this->renderFit(
                    source:
                        $source,

                    sourceWidth:
                        $sourceWidth,

                    sourceHeight:
                        $sourceHeight,

                    maximumWidth:
                        $targetWidth,

                    maximumHeight:
                        $targetHeight
                ),

            default =>
                throw new RuntimeException(
                    sprintf(
                        'Unsupported image rendition mode: %s.',
                        $mode
                    )
                ),
        };
    }

    /**
     * Create an exact-size center-cropped rendition.
     */
    private function renderCover(
        GdImage $source,
        int $sourceWidth,
        int $sourceHeight,
        int $targetWidth,
        int $targetHeight
    ): GdImage {
        $scale = max(
            $targetWidth
                / $sourceWidth,

            $targetHeight
                / $sourceHeight
        );

        $resizedWidth = max(
            1,
            (int) ceil(
                $sourceWidth * $scale
            )
        );

        $resizedHeight = max(
            1,
            (int) ceil(
                $sourceHeight * $scale
            )
        );

        $destinationX =
            (int) floor(
                (
                    $targetWidth
                    - $resizedWidth
                ) / 2
            );

        $destinationY =
            (int) floor(
                (
                    $targetHeight
                    - $resizedHeight
                ) / 2
            );

        $canvas =
            $this->createTransparentCanvas(
                width:
                    $targetWidth,

                height:
                    $targetHeight
            );

        $copied = imagecopyresampled(
            $canvas,
            $source,
            $destinationX,
            $destinationY,
            0,
            0,
            $resizedWidth,
            $resizedHeight,
            $sourceWidth,
            $sourceHeight
        );

        if (!$copied) {
            imagedestroy($canvas);

            throw new RuntimeException(
                'Unable to create the cropped image rendition.'
            );
        }

        return $canvas;
    }

    /**
     * Resize the complete image inside the maximum dimensions.
     *
     * Small images are not enlarged.
     */
    private function renderFit(
        GdImage $source,
        int $sourceWidth,
        int $sourceHeight,
        int $maximumWidth,
        int $maximumHeight
    ): GdImage {
        $scale = min(
            1,
            $maximumWidth
                / $sourceWidth,
            $maximumHeight
                / $sourceHeight
        );

        $targetWidth = max(
            1,
            (int) round(
                $sourceWidth * $scale
            )
        );

        $targetHeight = max(
            1,
            (int) round(
                $sourceHeight * $scale
            )
        );

        $canvas =
            $this->createTransparentCanvas(
                width:
                    $targetWidth,

                height:
                    $targetHeight
            );

        $copied = imagecopyresampled(
            $canvas,
            $source,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $sourceWidth,
            $sourceHeight
        );

        if (!$copied) {
            imagedestroy($canvas);

            throw new RuntimeException(
                'Unable to create the fitted image rendition.'
            );
        }

        return $canvas;
    }

    /**
     * Create a transparent true-color canvas.
     */
    private function createTransparentCanvas(
        int $width,
        int $height
    ): GdImage {
        $canvas =
            imagecreatetruecolor(
                $width,
                $height
            );

        if (!$canvas instanceof GdImage) {
            throw new RuntimeException(
                'Unable to allocate memory for image processing.'
            );
        }

        imagealphablending(
            $canvas,
            false
        );

        imagesavealpha(
            $canvas,
            true
        );

        $transparent =
            imagecolorallocatealpha(
                $canvas,
                0,
                0,
                0,
                127
            );

        imagefilledrectangle(
            $canvas,
            0,
            0,
            $width,
            $height,
            $transparent
        );

        imagealphablending(
            $canvas,
            true
        );

        if (
            function_exists(
                'imagesetinterpolation'
            )
        ) {
            imagesetinterpolation(
                $canvas,
                IMG_BICUBIC_FIXED
            );
        }

        return $canvas;
    }

    /**
     * Encode a GD image as WebP.
     */
    private function encodeWebp(
        GdImage $image,
        int $quality
    ): string {
        $temporaryPath =
            tempnam(
                sys_get_temp_dir(),
                'rushpi-media-'
            );

        if ($temporaryPath === false) {
            throw new RuntimeException(
                'Unable to create a temporary product image file.'
            );
        }

        try {
            $encoded = imagewebp(
                $image,
                $temporaryPath,
                max(
                    1,
                    min(
                        100,
                        $quality
                    )
                )
            );

            if (!$encoded) {
                throw new RuntimeException(
                    'Unable to encode the optimized product image as WebP.'
                );
            }

            $binary =
                file_get_contents(
                    $temporaryPath
                );

            if (
                $binary === false
                || $binary === ''
            ) {
                throw new RuntimeException(
                    'The optimized WebP image is empty.'
                );
            }

            return $binary;
        } finally {
            if (
                is_file(
                    $temporaryPath
                )
            ) {
                @unlink(
                    $temporaryPath
                );
            }
        }
    }

    /**
     * Build a deterministic, checksum-versioned rendition path.
     *
     * Using the checksum prevents an unsuccessful reprocessing attempt from
     * replacing files referenced by a previously completed media record.
     */
    private function renditionPath(
        ProductMedia $media,
        string $checksum,
        string $renditionName
    ): string {
        $productIdentifier =
            (string) (
                $media->product_id
                ?? 'unassigned'
            );

        $mediaIdentifier =
            trim(
                (string) (
                    $media->public_id
                    ?? $media->getKey()
                    ?? 'media'
                )
            );

        $mediaIdentifier =
            preg_replace(
                '/[^A-Za-z0-9_-]/',
                '',
                $mediaIdentifier
            )
            ?: 'media';

        $checksumVersion =
            substr(
                $checksum,
                0,
                16
            );

        return sprintf(
            'product-media/%s/%s/%s/%s.webp',
            $productIdentifier,
            $mediaIdentifier,
            $checksumVersion,
            $renditionName
        );
    }

    /**
     * Remove files created during an unsuccessful processing attempt.
     *
     * @param array<int, string> $paths
     */
    private function deletePaths(
        Filesystem $disk,
        array $paths
    ): void {
        if ($paths === []) {
            return;
        }

        try {
            $disk->delete(
                array_values(
                    array_unique(
                        $paths
                    )
                )
            );
        } catch (Throwable) {
            /*
             * Preserve the original processing exception. Temporary orphaned
             * files may be removed later by a cleanup job.
             */
        }
    }
}
