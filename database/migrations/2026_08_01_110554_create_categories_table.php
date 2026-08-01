<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the product categories table.
     */
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();

            $table->ulid('public_id')->unique();

            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('categories')
                ->nullOnDelete();

            $table->string('name', 150);

            $table->string('slug', 180)->unique();

            $table->text('description')->nullable();

            $table->string('image_path', 1000)->nullable();

            $table->boolean('is_active')->default(true);

            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->softDeletes();

            $table->index([
                'parent_id',
                'is_active',
            ]);

            $table->index([
                'is_active',
                'sort_order',
            ]);
        });
    }

    /**
     * Remove the product categories table.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};