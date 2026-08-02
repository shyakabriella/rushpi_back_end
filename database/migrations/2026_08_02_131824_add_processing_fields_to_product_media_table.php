<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add product-media processing and optimized rendition fields.
     */
    public function up(): void
    {
        Schema::table(
            'product_media',
            function (Blueprint $table): void {
                /*
                |--------------------------------------------------------------------------
                | Processing lifecycle
                |--------------------------------------------------------------------------
                */

                $table->string(
                    'processing_status',
                    32
                )
                    ->default('pending')
                    ->index();

                $table->unsignedSmallInteger(
                    'processing_attempts'
                )->default(0);

                $table->text(
                    'processing_error'
                )->nullable();

                /*
                |--------------------------------------------------------------------------
                | Original image information
                |--------------------------------------------------------------------------
                */

                $table->unsignedInteger(
                    'original_width'
                )->nullable();

                $table->unsignedInteger(
                    'original_height'
                )->nullable();

                $table->char(
                    'checksum_sha256',
                    64
                )
                    ->nullable()
                    ->index();

                /*
                |--------------------------------------------------------------------------
                | Generated image renditions
                |--------------------------------------------------------------------------
                |
                | Example structure:
                |
                | {
                |   "thumbnail": {
                |     "disk": "public",
                |     "path": "products/.../thumbnail.webp",
                |     "url": null,
                |     "width": 200,
                |     "height": 200,
                |     "size_bytes": 14230,
                |     "mime_type": "image/webp"
                |   },
                |   "card": {},
                |   "detail": {},
                |   "original_optimized": {}
                | }
                |
                */

                $table->json(
                    'renditions'
                )->nullable();

                /*
                |--------------------------------------------------------------------------
                | Processing timestamps
                |--------------------------------------------------------------------------
                */

                $table->timestamp(
                    'processing_started_at'
                )->nullable();

                $table->timestamp(
                    'last_processing_attempt_at'
                )->nullable();

                $table->timestamp(
                    'processed_at'
                )->nullable();

                $table->timestamp(
                    'processing_failed_at'
                )->nullable();

                /*
                |--------------------------------------------------------------------------
                | Query optimization
                |--------------------------------------------------------------------------
                */

                $table->index(
                    [
                        'product_id',
                        'processing_status',
                    ],
                    'product_media_product_processing_status_index'
                );
            }
        );
    }

    /**
     * Remove product-media processing fields.
     */
    public function down(): void
    {
        Schema::table(
            'product_media',
            function (Blueprint $table): void {
                $table->dropIndex(
                    'product_media_product_processing_status_index'
                );

                $table->dropIndex(
                    'product_media_processing_status_index'
                );

                $table->dropIndex(
                    'product_media_checksum_sha256_index'
                );

                $table->dropColumn([
                    'processing_status',
                    'processing_attempts',
                    'processing_error',
                    'original_width',
                    'original_height',
                    'checksum_sha256',
                    'renditions',
                    'processing_started_at',
                    'last_processing_attempt_at',
                    'processed_at',
                    'processing_failed_at',
                ]);
            }
        );
    }
};