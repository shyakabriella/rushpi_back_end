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
            'brand_series',
            function (Blueprint $table): void {
                $table->id();
                $table->ulid('public_id')->unique();

                $table->foreignId('brand_id')
                    ->constrained('brands')
                    ->cascadeOnDelete();

                $table->string('name', 150);
                $table->string('slug', 180);
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);

                $table->timestamps();
                $table->softDeletes();

                $table->unique([
                    'brand_id',
                    'name',
                ]);

                $table->unique([
                    'brand_id',
                    'slug',
                ]);

                $table->index([
                    'brand_id',
                    'is_active',
                    'sort_order',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('brand_series');
    }
};
