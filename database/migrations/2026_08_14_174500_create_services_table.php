<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table): void {
            $table->id();

            $table->uuid('public_id')->unique();

            // Allows other service types later.
            $table->string('service_type', 50)
                ->default('paint')
                ->index();

            $table->string('name');

            $table->string('slug')->unique();

            $table->string('paint_type')->nullable();

            $table->string('brand_name')->nullable();

            $table->string('color_name')->nullable();

            $table->text('description')->nullable();

            /*
             * Reference pricing.
             *
             * Examples:
             *
             * 1 L = 7,000 RWF
             * 20 L = 90,000 RWF
             * 1 KG = 8,000 RWF
             */
            $table->decimal(
                'reference_quantity',
                14,
                4
            );

            $table->string(
                'reference_unit',
                10
            );

            $table->decimal(
                'reference_price_rwf',
                18,
                2
            );

            /*
             * Required when customers
             * can convert between
             * weight and volume.
             *
             * Example:
             * 1.25 kg/L
             */
            $table->decimal(
                'density_kg_per_l',
                10,
                4
            )->nullable();

            $table->boolean(
                'allow_volume_sale'
            )->default(true);

            $table->boolean(
                'allow_weight_sale'
            )->default(false);

            $table->boolean(
                'allow_amount_sale'
            )->default(true);

            /*
             * Current available stock.
             *
             * Examples:
             * 50 L
             * 20000 mL
             * 25 KG
             */
            $table->decimal(
                'stock_quantity',
                16,
                4
            )->default(0);

            $table->string(
                'stock_unit',
                10
            );

            $table->string(
                'image_path'
            )->nullable();

            $table->boolean(
                'is_active'
            )->default(true);

            $table->string(
                'status',
                30
            )->default('active')
                ->index();

            $table->timestamps();

            $table->index([
                'service_type',
                'is_active',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};