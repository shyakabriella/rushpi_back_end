<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Enums\ProductMediaProcessingStatus;
use App\Jobs\ProcessProductMedia;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductMedia;
use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class ProductMediaApiTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Prepare isolated storage and prevent queued jobs from running.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        Queue::fake();

        config([
            'filesystems.product_media_disk' =>
                'public',
        ]);
    }

    /**
     * An unauthenticated user cannot upload product media.
     */
    public function test_unauthenticated_user_cannot_upload_product_media():
        void
    {
        [
            ,
            $seller,
            $product,
        ] = $this->createSellerProduct(
            'unauthenticated'
        );

        $response = $this->post(
            $this->mediaUploadUrl(
                $seller,
                $product
            ),
            [
                'image' =>
                    UploadedFile::fake()
                        ->image(
                            'phone.jpg',
                            1000,
                            800
                        ),
            ],
            [
                'Accept' =>
                    'application/json',
            ]
        );

        $response->assertUnauthorized();

        Queue::assertNothingPushed();

        $this->assertDatabaseCount(
            'product_media',
            0
        );
    }

    /**
     * An approved seller can upload an image and queue processing.
     */
    public function test_approved_seller_can_upload_product_image_and_queue_processing():
        void
    {
        [
            $user,
            $seller,
            $product,
        ] = $this->createSellerProduct(
            'approved-upload'
        );

        Sanctum::actingAs($user);

        $response = $this->post(
            $this->mediaUploadUrl(
                $seller,
                $product
            ),
            [
                'image' =>
                    UploadedFile::fake()
                        ->image(
                            'smartphone.jpg',
                            1600,
                            1200
                        )
                        ->size(500),

                'alt_text' =>
                    'Front view of smartphone',

                'is_primary' =>
                    true,
            ],
            [
                'Accept' =>
                    'application/json',
            ]
        );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonPath(
                'data.processing.status',
                ProductMediaProcessingStatus
                    ::PENDING
                    ->value
            )
            ->assertJsonPath(
                'data.is_primary',
                true
            )
            ->assertJsonPath(
                'data.alt_text',
                'Front view of smartphone'
            );

        $media = ProductMedia::query()
            ->first();

        $this->assertInstanceOf(
            ProductMedia::class,
            $media
        );

        $this->assertSame(
            (int) $product->getKey(),
            (int) $media->product_id
        );

        $this->assertTrue(
            (bool) $media->is_primary
        );

        $this->assertSame(
            ProductMediaProcessingStatus
                ::PENDING,
            $media->processing_status
        );

        $this->assertSame(
            0,
            (int) $media
                ->processing_attempts
        );

        $this->assertNull(
            $media->processing_error
        );

        $this->assertNull(
            $media->renditions
        );

        $this->assertNotNull(
            $media->originalPath()
        );

        Storage::disk(
            $media->originalDisk()
        )->assertExists(
            (string) $media
                ->originalPath()
        );

        Queue::assertPushedOn(
            'media',
            ProcessProductMedia::class,
            static function (
                ProcessProductMedia $job
            ) use ($media): bool {
                return (string) $job
                    ->mediaId
                    === (string) $media
                        ->getKey();
            }
        );
    }

    /**
     * Executable content disguised as an image is rejected.
     */
    public function test_executable_content_disguised_as_image_is_rejected():
        void
    {
        [
            $user,
            $seller,
            $product,
        ] = $this->createSellerProduct(
            'unsafe-upload'
        );

        Sanctum::actingAs($user);

        $unsafeFile =
            UploadedFile::fake()
                ->createWithContent(
                    'payload.php.jpg',
                    <<<'PHP'
<?php

echo 'unsafe executable content';
PHP
                );

        $response = $this->post(
            $this->mediaUploadUrl(
                $seller,
                $product
            ),
            [
                'image' =>
                    $unsafeFile,
            ],
            [
                'Accept' =>
                    'application/json',
            ]
        );

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'image',
            ]);

        Queue::assertNothingPushed();

        $this->assertDatabaseCount(
            'product_media',
            0
        );

        $this->assertSame(
            [],
            Storage::disk('public')
                ->allFiles()
        );
    }

    /**
     * A seller cannot upload media to another seller's product.
     */
    public function test_seller_cannot_upload_media_to_another_sellers_product():
        void
    {
        [
            ,
            $firstSeller,
            $firstProduct,
        ] = $this->createSellerProduct(
            'first-seller'
        );

        [
            $secondUser,
        ] = $this->createSellerProduct(
            'second-seller'
        );

        Sanctum::actingAs(
            $secondUser
        );

        $response = $this->post(
            $this->mediaUploadUrl(
                $firstSeller,
                $firstProduct
            ),
            [
                'image' =>
                    UploadedFile::fake()
                        ->image(
                            'unauthorized.jpg',
                            1000,
                            800
                        ),
            ],
            [
                'Accept' =>
                    'application/json',
            ]
        );

        /*
         * A middleware may return 403, while privacy-preserving scoped route
         * binding may return 404. Both safely deny cross-seller access.
         */
        $this->assertContains(
            $response->status(),
            [
                403,
                404,
            ]
        );

        Queue::assertNothingPushed();

        $this->assertDatabaseCount(
            'product_media',
            0
        );
    }

    /**
     * Failed image processing can be queued for another attempt.
     */
    public function test_seller_can_retry_failed_product_media_processing():
        void
    {
        [
            $user,
            $seller,
            $product,
        ] = $this->createSellerProduct(
            'retry-failed'
        );

        $media = $this->createStoredMedia(
            product: $product,
            status:
                ProductMediaProcessingStatus
                    ::FAILED,
            processingError:
                'Temporary image processing failure.'
        );

        Sanctum::actingAs($user);

        $response = $this->postJson(
            $this->mediaRetryUrl(
                $seller,
                $product,
                $media
            )
        );

        $response
            ->assertAccepted()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonPath(
                'data.processing.status',
                ProductMediaProcessingStatus
                    ::PENDING
                    ->value
            )
            ->assertJsonPath(
                'data.processing.error',
                null
            );

        $media->refresh();

        $this->assertSame(
            ProductMediaProcessingStatus
                ::PENDING,
            $media->processing_status
        );

        $this->assertNull(
            $media->processing_error
        );

        $this->assertNull(
            $media
                ->processing_started_at
        );

        $this->assertNull(
            $media
                ->processing_failed_at
        );

        Storage::disk(
            $media->originalDisk()
        )->assertExists(
            (string) $media
                ->originalPath()
        );

        Queue::assertPushedOn(
            'media',
            ProcessProductMedia::class,
            static function (
                ProcessProductMedia $job
            ) use ($media): bool {
                return (string) $job
                    ->mediaId
                    === (string) $media
                        ->getKey();
            }
        );
    }

    /**
     * Successfully processed media cannot be unnecessarily retried.
     */
    public function test_completed_product_media_cannot_be_retried():
        void
    {
        [
            $user,
            $seller,
            $product,
        ] = $this->createSellerProduct(
            'retry-completed'
        );

        $media = $this->createStoredMedia(
            product: $product,
            status:
                ProductMediaProcessingStatus
                    ::COMPLETED,
            renditions: [
                ProductMedia
                    ::RENDITION_CARD => [
                    'disk' =>
                        'public',

                    'path' =>
                        'product-media/testing/card.webp',

                    'url' =>
                        null,

                    'width' =>
                        600,

                    'height' =>
                        600,

                    'size_bytes' =>
                        12000,

                    'mime_type' =>
                        'image/webp',
                ],
            ]
        );

        Storage::disk('public')->put(
            'product-media/testing/card.webp',
            'fake-webp-content'
        );

        Sanctum::actingAs($user);

        $response = $this->postJson(
            $this->mediaRetryUrl(
                $seller,
                $product,
                $media
            )
        );

        $response
            ->assertConflict()
            ->assertJsonPath(
                'success',
                false
            );

        $media->refresh();

        $this->assertSame(
            ProductMediaProcessingStatus
                ::COMPLETED,
            $media->processing_status
        );

        Queue::assertNothingPushed();
    }

    /**
     * A recent active processing attempt cannot be duplicated.
     */
    public function test_currently_processing_media_cannot_be_queued_twice():
        void
    {
        [
            $user,
            $seller,
            $product,
        ] = $this->createSellerProduct(
            'processing-active'
        );

        $media = $this->createStoredMedia(
            product: $product,
            status:
                ProductMediaProcessingStatus
                    ::PROCESSING
        );

        $media->forceFill([
            'processing_started_at' =>
                now(),

            'last_processing_attempt_at' =>
                now(),
        ])->save();

        Sanctum::actingAs($user);

        $response = $this->postJson(
            $this->mediaRetryUrl(
                $seller,
                $product,
                $media
            )
        );

        $response
            ->assertConflict()
            ->assertJsonPath(
                'success',
                false
            );

        Queue::assertNothingPushed();
    }

    /**
     * Create an approved seller, active membership, category and draft product.
     *
     * @return array{
     *     0: User,
     *     1: SellerProfile,
     *     2: Product
     * }
     */
    private function createSellerProduct(
        string $suffix
    ): array {
        $suffix = Str::slug(
            $suffix
        );

        $userAttributes =
            $this->existingColumns(
                'users',
                [
                    'name' =>
                        'Seller '.$suffix,

                    'email' =>
                        "{$suffix}@example.com",

                    'email_verified_at' =>
                        now(),

                    'password' =>
                        bcrypt('Password123!'),

                    'role' =>
                        'customer',

                    'status' =>
                        'active',
                ]
            );

        $user = User::factory()
            ->create(
                $userAttributes
            );

        $seller = SellerProfile::query()
            ->forceCreate(
                $this->existingColumns(
                    'seller_profiles',
                    [
                        'public_id' =>
                            (string) Str::ulid(),

                        'user_id' =>
                            $user->getKey(),

                        'owner_user_id' =>
                            $user->getKey(),

                        'created_by' =>
                            $user->getKey(),

                        'legal_business_name' =>
                            'RushPi '.$suffix
                            .' Limited',

                        'trading_name' =>
                            'RushPi '.$suffix,

                        'registration_number' =>
                            strtoupper(
                                'RC-'.$suffix
                            ),

                        'tax_identification_number' =>
                            strtoupper(
                                'TIN-'.$suffix
                            ),

                        'business_type' =>
                            'company',

                        'business_email' =>
                            "business-{$suffix}@example.com",

                        'business_phone' =>
                            '+250788000000',

                        'country_code' =>
                            'RW',

                        'currency' =>
                            'RWF',

                        'status' =>
                            'approved',

                        'approved_at' =>
                            now(),

                        'approved_by' =>
                            $user->getKey(),
                    ]
                )
            );

        $this->createSellerMembership(
            $seller,
            $user
        );

        $category = Category::query()
            ->forceCreate(
                $this->existingColumns(
                    'categories',
                    [
                        'public_id' =>
                            (string) Str::ulid(),

                        'parent_id' =>
                            null,

                        'name' =>
                            'Electronics '.$suffix,

                        'slug' =>
                            'electronics-'.$suffix,

                        'description' =>
                            'Electronics test category.',

                        'image_path' =>
                            null,

                        'is_active' =>
                            true,

                        'sort_order' =>
                            0,
                    ]
                )
            );

        $product = Product::query()
            ->forceCreate(
                $this->existingColumns(
                    'products',
                    [
                        'public_id' =>
                            (string) Str::ulid(),

                        'seller_profile_id' =>
                            $seller->getKey(),

                        'category_id' =>
                            $category->getKey(),

                        'brand_id' =>
                            null,

                        'name' =>
                            'Smartphone '.$suffix,

                        'slug' =>
                            'smartphone-'.$suffix,

                        'short_description' =>
                            'A test smartphone.',

                        'description' =>
                            'A detailed test smartphone description.',

                        'condition' =>
                            'new',

                        'model_number' =>
                            strtoupper(
                                'MODEL-'.$suffix
                            ),

                        'status' =>
                            'draft',

                        'is_active' =>
                            true,
                    ]
                )
            );

        return [
            $user,
            $seller,
            $product,
        ];
    }

    /**
     * Create an active seller membership using the current schema.
     */
    private function createSellerMembership(
        SellerProfile $seller,
        User $user
    ): void {
        if (
            !Schema::hasTable(
                'seller_members'
            )
        ) {
            return;
        }

        DB::table(
            'seller_members'
        )->insert(
            $this->existingColumns(
                'seller_members',
                [
                    'public_id' =>
                        (string) Str::ulid(),

                    'seller_profile_id' =>
                        $seller->getKey(),

                    'user_id' =>
                        $user->getKey(),

                    'role' =>
                        'owner',

                    'status' =>
                        'active',

                    'joined_at' =>
                        now(),

                    'created_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]
            )
        );
    }

    /**
     * Create a stored product-media record for retry tests.
     *
     * @param array<string, array<string, mixed>>|null $renditions
     */
    private function createStoredMedia(
        Product $product,
        ProductMediaProcessingStatus $status,
        ?string $processingError = null,
        ?array $renditions = null
    ): ProductMedia {
        $publicId =
            (string) Str::ulid();

        $path = sprintf(
            'product-media/testing/%s/original.jpg',
            $publicId
        );

        $uploadedImage =
            UploadedFile::fake()
                ->image(
                    'original.jpg',
                    1000,
                    800
                );

        $binary = file_get_contents(
            $uploadedImage->getRealPath()
        );

        $this->assertIsString(
            $binary
        );

        Storage::disk('public')->put(
            $path,
            $binary
        );

        $payload = [
            'public_id' =>
                $publicId,

            'product_id' =>
                $product->getKey(),

            'product_variant_id' =>
                null,

            'media_type' =>
                'image',

            'original_name' =>
                'original.jpg',

            'mime_type' =>
                'image/jpeg',

            'size_bytes' =>
                strlen($binary),

            'alt_text' =>
                'Stored product image',

            'metadata' =>
                [],

            'is_primary' =>
                true,

            'sort_order' =>
                0,

            'processing_status' =>
                $status->value,

            'processing_attempts' =>
                $status ===
                    ProductMediaProcessingStatus
                        ::PENDING
                    ? 0
                    : 1,

            'processing_error' =>
                $processingError,

            'original_width' =>
                $status ===
                    ProductMediaProcessingStatus
                        ::COMPLETED
                    ? 1000
                    : null,

            'original_height' =>
                $status ===
                    ProductMediaProcessingStatus
                        ::COMPLETED
                    ? 800
                    : null,

            'checksum_sha256' =>
                $status ===
                    ProductMediaProcessingStatus
                        ::COMPLETED
                    ? hash(
                        'sha256',
                        $binary
                    )
                    : null,

            'renditions' =>
                $renditions,

            'processing_started_at' =>
                $status ===
                    ProductMediaProcessingStatus
                        ::PROCESSING
                    ? now()
                    : null,

            'last_processing_attempt_at' =>
                $status ===
                    ProductMediaProcessingStatus
                        ::PENDING
                    ? null
                    : now(),

            'processed_at' =>
                $status ===
                    ProductMediaProcessingStatus
                        ::COMPLETED
                    ? now()
                    : null,

            'processing_failed_at' =>
                $status ===
                    ProductMediaProcessingStatus
                        ::FAILED
                    ? now()
                    : null,
        ];

        $payload = array_merge(
            $payload,
            $this->productMediaStorageColumns(
                disk: 'public',
                path: $path
            )
        );

        return ProductMedia::query()
            ->forceCreate(
                $this->existingColumns(
                    'product_media',
                    $payload
                )
            );
    }

    /**
     * Return the correct storage columns for the current media schema.
     *
     * @return array<string, string>
     */
    private function productMediaStorageColumns(
        string $disk,
        string $path
    ): array {
        $columns = [];

        if (
            Schema::hasColumn(
                'product_media',
                'storage_disk'
            )
        ) {
            $columns['storage_disk'] =
                $disk;
        }

        if (
            Schema::hasColumn(
                'product_media',
                'storage_path'
            )
        ) {
            $columns['storage_path'] =
                $path;
        }

        if (
            Schema::hasColumn(
                'product_media',
                'disk'
            )
        ) {
            $columns['disk'] =
                $disk;
        }

        if (
            Schema::hasColumn(
                'product_media',
                'path'
            )
        ) {
            $columns['path'] =
                $path;
        }

        $this->assertNotSame(
            [],
            $columns,
            'No supported product-media storage columns were found.'
        );

        return $columns;
    }

    /**
     * Keep only values supported by the current database table.
     *
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    private function existingColumns(
        string $table,
        array $values
    ): array {
        $columns = array_flip(
            Schema::getColumnListing(
                $table
            )
        );

        return array_intersect_key(
            $values,
            $columns
        );
    }

    /**
     * Build the product-media upload URL.
     */
    private function mediaUploadUrl(
        SellerProfile $seller,
        Product $product
    ): string {
        return sprintf(
            '/api/seller/profiles/%s/products/%s/media',
            $seller->public_id,
            $product->public_id
        );
    }

    /**
     * Build the product-media processing retry URL.
     */
    private function mediaRetryUrl(
        SellerProfile $seller,
        Product $product,
        ProductMedia $media
    ): string {
        return sprintf(
            '/api/seller/profiles/%s/products/%s/media/%s/retry-processing',
            $seller->public_id,
            $product->public_id,
            $media->public_id
        );
    }
}
