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
            'service_orders',
            function (Blueprint $table): void {
                $table->unsignedInteger('item_count')
                    ->default(1);

                $table->decimal(
                    'subtotal_amount_rwf',
                    18,
                    2
                )->nullable();

                $table->string(
                    'delivery_method',
                    30
                )->nullable();

                $table->decimal(
                    'delivery_fee_rwf',
                    18,
                    2
                )->default(0);

                $table->decimal(
                    'total_amount_rwf',
                    18,
                    2
                )->nullable();

                $table->decimal(
                    'delivery_latitude',
                    10,
                    7
                )->nullable();

                $table->decimal(
                    'delivery_longitude',
                    10,
                    7
                )->nullable();

                $table->string(
                    'delivery_city',
                    150
                )->nullable();

                $table->string(
                    'delivery_district',
                    150
                )->nullable();

                $table->string(
                    'delivery_region',
                    150
                )->nullable();

                $table->string(
                    'delivery_country',
                    150
                )->nullable();

                $table->boolean(
                    'is_kigali'
                )->nullable();

                $table->text(
                    'location_note'
                )->nullable();
            }
        );

        Schema::create(
            'service_order_items',
            function (Blueprint $table): void {
                $table->id();

                $table->uuid(
                    'public_id'
                )->unique();

                $table->foreignId(
                    'service_order_id'
                )
                    ->constrained(
                        'service_orders'
                    )
                    ->cascadeOnDelete();

                $table->foreignId(
                    'service_id'
                )
                    ->constrained(
                        'services'
                    )
                    ->restrictOnDelete();

                $table->string(
                    'service_name'
                );

                $table->string(
                    'paint_type',
                    150
                )->nullable();

                $table->string(
                    'color_name',
                    150
                )->nullable();

                $table->string(
                    'order_mode',
                    20
                );

                $table->decimal(
                    'requested_quantity',
                    18,
                    6
                )->nullable();

                $table->string(
                    'requested_unit',
                    10
                )->nullable();

                $table->decimal(
                    'requested_amount_rwf',
                    18,
                    2
                )->nullable();

                $table->decimal(
                    'equivalent_ml',
                    18,
                    6
                )->nullable();

                $table->decimal(
                    'equivalent_l',
                    18,
                    6
                )->nullable();

                $table->decimal(
                    'equivalent_g',
                    18,
                    6
                )->nullable();

                $table->decimal(
                    'equivalent_kg',
                    18,
                    6
                )->nullable();

                $table->decimal(
                    'reference_quantity',
                    18,
                    6
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

                $table->decimal(
                    'density_kg_per_l',
                    12,
                    6
                )->nullable();

                $table->decimal(
                    'stock_quantity_deducted',
                    18,
                    6
                );

                $table->string(
                    'stock_unit',
                    10
                );

                $table->decimal(
                    'line_total_rwf',
                    18,
                    2
                );

                $table->timestamps();

                $table->index([
                    'service_order_id',
                    'service_id',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'service_order_items'
        );

        Schema::table(
            'service_orders',
            function (Blueprint $table): void {
                $table->dropColumn([
                    'item_count',
                    'subtotal_amount_rwf',
                    'delivery_method',
                    'delivery_fee_rwf',
                    'total_amount_rwf',
                    'delivery_latitude',
                    'delivery_longitude',
                    'delivery_city',
                    'delivery_district',
                    'delivery_region',
                    'delivery_country',
                    'is_kigali',
                    'location_note',
                ]);
            }
        );
    }
};
