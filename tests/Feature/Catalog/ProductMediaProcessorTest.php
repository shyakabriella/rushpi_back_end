<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Enums\ProductMediaProcessingStatus;
use App\Http\Resources\PublicProductMediaResource;
use App\Models\ProductMedia;
use App\Services\Catalog\ProductMediaProcessor;
use GdImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

final class ProductMediaProcessorTest extends TestCase
{
    /**
     * Prepare an isolated fake storage disk.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        if (
            !extension_loaded('gd')
            || !function_exists('imagewebp')
            || !function_exists('imagecreatefromstring')
        ) {
            $this->markTestSkipped(
                'PHP GD with WebP support is required.'
            );
        }

        if (
            defined('IMG_WEBP')
            && (
                imagetypes()
                & IMG_WEBP
            ) !== IMG_WEBP
        ) {
            $this->markTestSkipped(
                'The installed GD extension does not support WebP.'
            );
        }
    }

    /**
     * Verify that all optimized image versions are generated.
     */
    public function test_it_generates_all_required_webp_renditions():
        void
    {
        $originalPath =
            'product-media/testing/originals/product.jpg';

        Storage::disk('public')->put(
            $originalPath,
            $this->createJpegBinary(
                width: 1600,
                height: 1200
            )
        );

        $media =
            $this->createMedia(
                path: $originalPath,
                mimeType: 'image/jpeg'
            );

        $processor = app(
            ProductMediaProcessor::class
        );

        $result = $processor->process(
            $media
        );

        $this->assertSame(
            1600,
            $result['original_width']
        );

        $this->assertSame(
            1200,
            $result['original_height']
        );

        $this->assertSame(
            'image/jpeg',
            $result['detected_mime_type']
        );

        $this->assertMatchesRegularExpression(
            '/^[a-f0-9]{64}$/',
            $result['checksum_sha256']
        );

        $this->assertSame(
            [
                ProductMedia::RENDITION_THUMBNAIL,
                ProductMedia::RENDITION_CARD,
                ProductMedia::RENDITION_DETAIL,
                ProductMedia::RENDITION_ORIGINAL_OPTIMIZED,
            ],
            array_keys(
                $result['renditions']
            )
        );

        $this->assertRendition(
            $result['renditions'][
                ProductMedia::RENDITION_THUMBNAIL
            ],
            expectedWidth: 200,
            expectedHeight: 200
        );

        $this->assertRendition(
            $result['renditions'][
                ProductMedia::RENDITION_CARD
            ],
            expectedWidth: 600,
            expectedHeight: 600
        );

        $this->assertRendition(
            $result['renditions'][
                ProductMedia::RENDITION_DETAIL
            ],
            expectedWidth: 1200,
            expectedHeight: 900
        );

        /*
         * The optimized-original rendition must not enlarge an image that is
         * already smaller than its 2000 × 2000 maximum.
         */
        $this->assertRendition(
            $result['renditions'][
                ProductMedia::RENDITION_ORIGINAL_OPTIMIZED
            ],
            expectedWidth: 1600,
            expectedHeight: 1200
        );

        $this->assertTrue(
            Storage::disk('public')
                ->exists($originalPath)
        );

        $this->assertCount(
            5,
            Storage::disk('public')
                ->allFiles()
        );
    }

    /**
     * Verify that executable content disguised as an image is rejected.
     */
    public function test_it_rejects_executable_content_disguised_as_image():
        void
    {
        $originalPath =
            'product-media/testing/originals/unsafe.jpg';

        Storage::disk('public')->put(
            $originalPath,
            <<<'PHP'
<?php

echo 'This is not a product image.';
PHP
        );

        $media =
            $this->createMedia(
                path: $originalPath,
                mimeType: 'image/jpeg'
            );

        $processor = app(
            ProductMediaProcessor::class
        );

        $exception = null;

        try {
            $processor->process(
                $media
            );
        } catch (RuntimeException $caught) {
            $exception = $caught;
        }

        $this->assertInstanceOf(
            RuntimeException::class,
            $exception
        );

        $this->assertNotSame(
            '',
            trim(
                (string) $exception?->getMessage()
            )
        );

        /*
         * No optimized files should remain after validation failure.
         */
        $this->assertSame(
            [
                $originalPath,
            ],
            Storage::disk('public')
                ->allFiles()
        );
    }

    /**
     * Verify that SVG files are never accepted by the image processor.
     */
    public function test_it_rejects_svg_content():
        void
    {
        $originalPath =
            'product-media/testing/originals/unsafe.svg';

        Storage::disk('public')->put(
            $originalPath,
            <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100">
    <script>alert("unsafe")</script>
    <rect width="100" height="100" />
</svg>
SVG
        );

        $media =
            $this->createMedia(
                path: $originalPath,
                mimeType: 'image/svg+xml'
            );

        $processor = app(
            ProductMediaProcessor::class
        );

        $exception = null;

        try {
            $processor->process(
                $media
            );
        } catch (RuntimeException $caught) {
            $exception = $caught;
        }

        $this->assertInstanceOf(
            RuntimeException::class,
            $exception
        );

        $this->assertSame(
            [
                $originalPath,
            ],
            Storage::disk('public')
                ->allFiles()
        );
    }

    /**
     * Verify that the public resource exposes optimized images only.
     */
    public function test_public_media_resource_never_exposes_original_storage():
        void
    {
        $originalPath =
            'product-media/testing/originals/product.jpg';

        Storage::disk('public')->put(
            $originalPath,
            $this->createJpegBinary(
                width: 1600,
                height: 1200
            )
        );

        $media =
            $this->createMedia(
                path: $originalPath,
                mimeType: 'image/jpeg'
            );

        $processor = app(
            ProductMediaProcessor::class
        );

        $result = $processor->process(
            $media
        );

        $media->forceFill([
            'processing_status' =>
                ProductMediaProcessingStatus
                    ::COMPLETED
                    ->value,

            'processing_attempts' =>
                1,

            'original_width' =>
                $result['original_width'],

            'original_height' =>
                $result['original_height'],

            'checksum_sha256' =>
                $result['checksum_sha256'],

            'renditions' =>
                $result['renditions'],

            'processed_at' =>
                now(),
        ]);

        $resource = (
            new PublicProductMediaResource(
                $media
            )
        )->resolve(
            request()
        );

        $expectedCardUrl =
            $media->renditionUrl(
                ProductMedia::RENDITION_CARD
            );

        $expectedDetailUrl =
            $media->renditionUrl(
                ProductMedia::RENDITION_DETAIL
            );

        $this->assertSame(
            $expectedCardUrl,
            $resource['url']
        );

        $this->assertSame(
            $expectedCardUrl,
            $resource['urls']['card']
        );

        $this->assertSame(
            $expectedDetailUrl,
            $resource['urls']['detail']
        );

        $this->assertSame(
            'image/webp',
            $resource[
                'renditions'
            ][
                'card'
            ][
                'mime_type'
            ]
        );

        /*
         * Seller-only and storage-only values must not appear publicly.
         */
        $this->assertArrayNotHasKey(
            'original',
            $resource
        );

        $this->assertArrayNotHasKey(
            'processing',
            $resource
        );

        $this->assertArrayNotHasKey(
            'processing_error',
            $resource
        );

        $this->assertArrayNotHasKey(
            'disk',
            $resource[
                'renditions'
            ][
                'card'
            ]
        );

        $this->assertArrayNotHasKey(
            'path',
            $resource[
                'renditions'
            ][
                'card'
            ]
        );

        $encodedResource = json_encode(
            $resource,
            JSON_THROW_ON_ERROR
        );

        $this->assertStringNotContainsString(
            $originalPath,
            $encodedResource
        );

        $this->assertStringNotContainsString(
            '"storage_disk"',
            $encodedResource
        );

        $this->assertStringNotContainsString(
            '"storage_path"',
            $encodedResource
        );
    }

    /**
     * Verify that pending media never falls back to the original upload.
     */
    public function test_pending_media_has_no_public_image_url():
        void
    {
        $originalPath =
            'product-media/testing/originals/pending.jpg';

        Storage::disk('public')->put(
            $originalPath,
            $this->createJpegBinary(
                width: 800,
                height: 600
            )
        );

        $media =
            $this->createMedia(
                path: $originalPath,
                mimeType: 'image/jpeg'
            );

        $resource = (
            new PublicProductMediaResource(
                $media
            )
        )->resolve(
            request()
        );

        $this->assertNull(
            $resource['url']
        );

        $this->assertNull(
            $resource['urls']['thumbnail']
        );

        $this->assertNull(
            $resource['urls']['card']
        );

        $this->assertNull(
            $resource['urls']['detail']
        );

        $this->assertNull(
            $resource['urls'][
                'original_optimized'
            ]
        );

        $encodedResource = json_encode(
            $resource,
            JSON_THROW_ON_ERROR
        );

        $this->assertStringNotContainsString(
            $originalPath,
            $encodedResource
        );
    }

    /**
     * Create an unsaved product-media model for processor testing.
     */
    private function createMedia(
        string $path,
        string $mimeType
    ): ProductMedia {
        $media = new ProductMedia();

        $media->forceFill([
            'public_id' =>
                (string) Str::ulid(),

            /*
             * No database record is needed by ProductMediaProcessor. The
             * value is used only when building the rendition directory.
             */
            'product_id' =>
                1001,

            'product_variant_id' =>
                null,

            'media_type' =>
                'image',

            'storage_disk' =>
                'public',

            'storage_path' =>
                $path,

            'original_name' =>
                basename($path),

            'mime_type' =>
                $mimeType,

            'size_bytes' =>
                Storage::disk('public')
                    ->size($path),

            'alt_text' =>
                'Test product image',

            'is_primary' =>
                true,

            'sort_order' =>
                0,

            'processing_status' =>
                ProductMediaProcessingStatus
                    ::PENDING
                    ->value,

            'processing_attempts' =>
                0,

            'renditions' =>
                null,
        ]);

        return $media;
    }

    /**
     * Assert that one rendition exists and has the expected properties.
     *
     * @param array<string, mixed> $rendition
     */
    private function assertRendition(
        array $rendition,
        int $expectedWidth,
        int $expectedHeight
    ): void {
        $this->assertSame(
            'public',
            $rendition['disk']
        );

        $this->assertSame(
            'image/webp',
            $rendition['mime_type']
        );

        $this->assertSame(
            $expectedWidth,
            $rendition['width']
        );

        $this->assertSame(
            $expectedHeight,
            $rendition['height']
        );

        $this->assertGreaterThan(
            0,
            $rendition['size_bytes']
        );

        $this->assertIsString(
            $rendition['path']
        );

        $this->assertStringEndsWith(
            '.webp',
            $rendition['path']
        );

        $this->assertTrue(
            Storage::disk(
                $rendition['disk']
            )->exists(
                $rendition['path']
            )
        );

        $binary = Storage::disk(
            $rendition['disk']
        )->get(
            $rendition['path']
        );

        $imageInformation =
            getimagesizefromstring(
                $binary
            );

        $this->assertIsArray(
            $imageInformation
        );

        $this->assertSame(
            $expectedWidth,
            (int) $imageInformation[0]
        );

        $this->assertSame(
            $expectedHeight,
            (int) $imageInformation[1]
        );

        $this->assertSame(
            'image/webp',
            $imageInformation['mime']
        );
    }

    /**
     * Generate a genuine JPEG image for testing.
     */
    private function createJpegBinary(
        int $width,
        int $height
    ): string {
        $image = imagecreatetruecolor(
            $width,
            $height
        );

        $this->assertInstanceOf(
            GdImage::class,
            $image
        );

        $background =
            imagecolorallocate(
                $image,
                230,
                235,
                240
            );

        $foreground =
            imagecolorallocate(
                $image,
                40,
                60,
                80
            );

        imagefilledrectangle(
            $image,
            0,
            0,
            $width,
            $height,
            $background
        );

        imagefilledellipse(
            $image,
            (int) ($width / 2),
            (int) ($height / 2),
            max(
                20,
                (int) ($width / 2)
            ),
            max(
                20,
                (int) ($height / 2)
            ),
            $foreground
        );

        ob_start();

        try {
            $encoded = imagejpeg(
                $image,
                null,
                90
            );

            $binary = ob_get_clean();
        } finally {
            imagedestroy($image);
        }

        $this->assertTrue(
            $encoded
        );

        $this->assertIsString(
            $binary
        );

        $this->assertNotSame(
            '',
            $binary
        );

        return $binary;
    }
}
