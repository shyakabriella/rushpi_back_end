<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the product brands table.
     */
    public function up(): void
    {
        Schema::create('brands', function (Blueprint $table): void {
            $table->id();

            $table->ulid('public_id')->unique();

            $table->string('name', 150)->unique();

            $table->string('slug', 180)->unique();

            $table->text('description')->nullable();

            $table->string('logo_path', 1000)->nullable();

            $table->string('website_url', 1000)->nullable();

            $table->boolean('is_active')->default(true);

            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            $table->softDeletes();

            $table->index([
                'is_active',
                'sort_order',
            ]);
        });
    }

    /**
     * Remove the product brands table.
     */
    public function down(): void
    {
        Schema::dropIfExists('brands');
    }
};