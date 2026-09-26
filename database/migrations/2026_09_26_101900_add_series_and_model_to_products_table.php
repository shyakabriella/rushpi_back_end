<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'products',
            function (Blueprint $table): void {
                $table->foreignId('brand_series_id')
                    ->nullable()
                    ->after('brand_id')
                    ->constrained('brand_series')
                    ->nullOnDelete();

                $table->foreignId('brand_model_id')
                    ->nullable()
                    ->after('brand_series_id')
                    ->constrained('brand_models')
                    ->nullOnDelete();

                $table->index(
                    [
                        'brand_id',
                        'brand_series_id',
                        'brand_model_id',
                    ],
                    'products_brand_catalog_index'
                );
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'products',
            function (Blueprint $table): void {
                $table->dropIndex(
                    'products_brand_catalog_index'
                );

                $table->dropConstrainedForeignId(
                    'brand_model_id'
                );

                $table->dropConstrainedForeignId(
                    'brand_series_id'
                );
            }
        );
    }
};
