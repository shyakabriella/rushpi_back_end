<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'brand_models',
            function (Blueprint $table): void {
                $table->id();
                $table->ulid('public_id')->unique();

                $table->foreignId('brand_id')
                    ->constrained('brands')
                    ->cascadeOnDelete();

                $table->foreignId('brand_series_id')
                    ->constrained('brand_series')
                    ->cascadeOnDelete();

                $table->string('name', 180);
                $table->string('slug', 200);
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);

                $table->timestamps();
                $table->softDeletes();

                $table->unique([
                    'brand_series_id',
                    'name',
                ]);

                $table->unique([
                    'brand_series_id',
                    'slug',
                ]);

                $table->index([
                    'brand_id',
                    'brand_series_id',
                    'is_active',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_models');
    }
};
