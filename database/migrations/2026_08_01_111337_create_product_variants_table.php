<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the product variants table.
     */
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table): void {
            $table->id();

            $table->ulid('public_id')->unique();

            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();

            /*
             * Seller-defined stock keeping unit.
             *
             * The same SKU cannot be repeated inside one product,
             * but different sellers may use the same SKU.
             */
            $table->string('sku', 150);

            $table->string('barcode', 150)
                ->nullable();

            $table->string('name', 255);

            /*
             * Example:
             * {
             *   "color": "Black Titanium",
             *   "storage": "256 GB",
             *   "ram": "8 GB"
             * }
             */
            $table->json('attributes')
                ->nullable();

            $table->unsignedInteger('weight_grams')
                ->nullable();

            $table->decimal('length_cm', 10, 2)
                ->nullable();

            $table->decimal('width_cm', 10, 2)
                ->nullable();

            $table->decimal('height_cm', 10, 2)
                ->nullable();

            $table->boolean('is_default')
                ->default(false);

            $table->boolean('is_active')
                ->default(true);

            $table->unsignedInteger('sort_order')
                ->default(0);

            $table->timestamps();

            $table->softDeletes();

            $table->unique(
                ['product_id', 'sku'],
                'product_variants_product_sku_unique'
            );

            $table->index('sku');

            $table->index('barcode');

            $table->index([
                'product_id',
                'is_active',
            ]);

            $table->index([
                'product_id',
                'is_default',
            ]);

            $table->index([
                'product_id',
                'sort_order',
            ]);
        });
    }

    /**
     * Remove the product variants table.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};