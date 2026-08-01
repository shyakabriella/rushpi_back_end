<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the seller products table.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();

            $table->ulid('public_id')->unique();

            $table->foreignId('seller_profile_id')
                ->constrained('seller_profiles')
                ->restrictOnDelete();

            $table->foreignId('category_id')
                ->constrained('categories')
                ->restrictOnDelete();

            $table->foreignId('brand_id')
                ->nullable()
                ->constrained('brands')
                ->nullOnDelete();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('name', 255);

            $table->string('slug', 255);

            $table->string('short_description', 500)
                ->nullable();

            $table->longText('description')
                ->nullable();

            $table->string('condition', 30)
                ->default('new');

            $table->unsignedSmallInteger('warranty_months')
                ->nullable();

            $table->json('specifications')
                ->nullable();

            $table->string('status', 30)
                ->default('draft');

            $table->text('rejection_reason')
                ->nullable();

            $table->text('suspension_reason')
                ->nullable();

            $table->timestamp('submitted_at')
                ->nullable();

            $table->timestamp('approved_at')
                ->nullable();

            $table->timestamp('rejected_at')
                ->nullable();

            $table->timestamp('suspended_at')
                ->nullable();

            $table->timestamp('archived_at')
                ->nullable();

            $table->timestamps();

            $table->softDeletes();

            $table->unique(
                ['seller_profile_id', 'slug'],
                'products_seller_slug_unique'
            );

            $table->index([
                'seller_profile_id',
                'status',
            ]);

            $table->index([
                'category_id',
                'status',
            ]);

            $table->index([
                'brand_id',
                'status',
            ]);

            $table->index([
                'status',
                'approved_at',
            ]);

            $table->index([
                'condition',
                'status',
            ]);

            $table->index('name');
        });
    }

    /**
     * Remove the seller products table.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};