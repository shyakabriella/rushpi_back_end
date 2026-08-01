<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the current inventory record
     * for each product variant.
     */
    public function up(): void
    {
        Schema::create('inventory_stocks', function (Blueprint $table): void {
            $table->id();

            /*
             * Each product variant has only one
             * current inventory record.
             */
            $table->foreignId('product_variant_id')
                ->unique()
                ->constrained('product_variants')
                ->cascadeOnDelete();

            /*
             * Total physical quantity currently held.
             */
            $table->unsignedBigInteger('quantity_on_hand')
                ->default(0);

            /*
             * Quantity temporarily reserved for
             * pending or confirmed customer orders.
             */
            $table->unsignedBigInteger('quantity_reserved')
                ->default(0);

            /*
             * Inventory level at which the seller
             * should receive a low-stock warning.
             */
            $table->unsignedBigInteger('reorder_level')
                ->default(0);

            /*
             * When false, available stock must never
             * become negative.
             */
            $table->boolean('allow_backorder')
                ->default(false);

            $table->timestamps();

            $table->index([
                'quantity_on_hand',
                'quantity_reserved',
            ]);

            $table->index([
                'reorder_level',
                'allow_backorder',
            ]);
        });
    }

    /**
     * Remove current product inventory records.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_stocks');
    }
};