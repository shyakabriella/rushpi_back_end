<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the product media table.
     */
    public function up(): void
    {
        Schema::create('product_media', function (Blueprint $table): void {
            $table->id();

            $table->ulid('public_id')->unique();

            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            /*
             * Optional variant connection.
             *
             * When null, the image belongs to the general product.
             * When provided, the image belongs to a specific variant.
             */
            $table->foreignId('product_variant_id')
                ->nullable()
                ->constrained('product_variants')
                ->nullOnDelete();

            $table->foreignId('uploaded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('media_type', 30)
                ->default('image');

            /*
             * Laravel storage disk, for example:
             * public, s3 or products.
             */
            $table->string('disk', 100)
                ->default('public');

            /*
             * Stored file location relative to the selected disk.
             */
            $table->string('path', 1000);

            $table->string('original_name', 255);

            $table->string('mime_type', 100);

            $table->unsignedBigInteger('size_bytes');

            $table->string('alt_text', 255)
                ->nullable();

            $table->unsignedInteger('sort_order')
                ->default(0);

            $table->boolean('is_primary')
                ->default(false);

            $table->timestamps();

            $table->softDeletes();

            $table->index([
                'product_id',
                'media_type',
            ]);

            $table->index([
                'product_id',
                'is_primary',
            ]);

            $table->index([
                'product_id',
                'sort_order',
            ]);

            $table->index([
                'product_variant_id',
                'sort_order',
            ]);
        });
    }

    /**
     * Remove the product media table.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_media');
    }
};