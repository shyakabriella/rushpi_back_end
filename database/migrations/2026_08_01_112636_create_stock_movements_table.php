<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the immutable inventory movement history.
     */
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();

            $table->ulid('public_id')->unique();

            $table->foreignId('product_variant_id')
                ->constrained('product_variants')
                ->restrictOnDelete();

            $table->foreignId('seller_profile_id')
                ->constrained('seller_profiles')
                ->restrictOnDelete();

            $table->foreignId('performed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('movement_type', 50);

            /*
             * Signed inventory change:
             *  10 means stock increased by 10.
             * -10 means stock decreased by 10.
             *
             * Reservation movements may change reserved stock
             * without changing quantity on hand.
             */
            $table->bigInteger('quantity');

            $table->unsignedBigInteger('quantity_on_hand_before');

            $table->unsignedBigInteger('quantity_on_hand_after');

            $table->unsignedBigInteger('quantity_reserved_before')
                ->default(0);

            $table->unsignedBigInteger('quantity_reserved_after')
                ->default(0);

            /*
             * Optional connection to an order, return,
             * purchase, adjustment or another future record.
             */
            $table->string('reference_type', 255)
                ->nullable();

            $table->unsignedBigInteger('reference_id')
                ->nullable();

            $table->string('reason', 1000)
                ->nullable();

            $table->json('metadata')
                ->nullable();

            /*
             * Stock movements are historical records.
             * They should never be edited after creation.
             */
            $table->timestamp('created_at')
                ->useCurrent();

            $table->index([
                'product_variant_id',
                'created_at',
            ]);

            $table->index([
                'seller_profile_id',
                'created_at',
            ]);

            $table->index([
                'movement_type',
                'created_at',
            ]);

            $table->index(
                ['reference_type', 'reference_id'],
                'stock_movements_reference_index'
            );
        });
    }

    /**
     * Remove the inventory movement history.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};