<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_orders', function (Blueprint $table): void {
            $table->id();

            $table->uuid('public_id')->unique();

            $table->string('order_number', 40)
                ->unique();

            /*
            |--------------------------------------------------------------------------
            | Customer
            |--------------------------------------------------------------------------
            */

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('customer_name', 150);
            $table->string('customer_phone', 50)->nullable();

            /*
            |--------------------------------------------------------------------------
            | Paint / service
            |--------------------------------------------------------------------------
            */

            $table->foreignId('service_id')
                ->constrained('services')
                ->restrictOnDelete();

            /*
             * Snapshot of the paint name at ordering time.
             */
            $table->string('service_name');

            /*
            |--------------------------------------------------------------------------
            | Customer order selection
            |--------------------------------------------------------------------------
            |
            | volume:
            | 250 ml
            | 1.5 l
            |
            | weight:
            | 500 g
            | 2 kg
            |
            | amount:
            | 1000 RWF -> calculated quantity
            |
            */

            $table->string('order_mode', 20);

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

            /*
            |--------------------------------------------------------------------------
            | Calculated equivalents
            |--------------------------------------------------------------------------
            */

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

            /*
            |--------------------------------------------------------------------------
            | Reference price snapshot
            |--------------------------------------------------------------------------
            */

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

            /*
            |--------------------------------------------------------------------------
            | Stock snapshot / deduction
            |--------------------------------------------------------------------------
            */

            $table->decimal(
                'stock_quantity_deducted',
                18,
                6
            );

            $table->string(
                'stock_unit',
                10
            );

            /*
            |--------------------------------------------------------------------------
            | Final amount
            |--------------------------------------------------------------------------
            */

            $table->decimal(
                'total_price_rwf',
                18,
                2
            );

            /*
            |--------------------------------------------------------------------------
            | Order state
            |--------------------------------------------------------------------------
            */

            $table->string(
                'status',
                30
            )->default('pending')->index();

            $table->string(
                'payment_status',
                30
            )->default('unpaid')->index();

            $table->text(
                'delivery_address'
            )->nullable();

            $table->text(
                'customer_note'
            )->nullable();

            $table->timestamp(
                'confirmed_at'
            )->nullable();

            $table->timestamp(
                'completed_at'
            )->nullable();

            $table->timestamp(
                'cancelled_at'
            )->nullable();

            $table->timestamps();

            $table->index([
                'user_id',
                'status',
            ]);

            $table->index([
                'service_id',
                'status',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'service_orders'
        );
    }
};
