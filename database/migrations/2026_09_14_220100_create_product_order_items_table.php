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
            'product_order_items',
            function (Blueprint $table): void {
                $table->id();

                $table->foreignId('product_order_id')
                    ->constrained('product_orders')
                    ->cascadeOnDelete();

                $table->foreignId('seller_profile_id')
                    ->constrained('seller_profiles')
                    ->restrictOnDelete();

                $table->foreignId('product_id')
                    ->constrained('products')
                    ->restrictOnDelete();

                $table->foreignId('product_variant_id')
                    ->constrained('product_variants')
                    ->restrictOnDelete();

                /*
                 * Product snapshots preserve order history
                 * when catalog details change later.
                 */
                $table->string('product_name', 255);
                $table->string('variant_name', 255);
                $table->string('sku', 150);

                $table->char('currency', 3)
                    ->default('RWF');

                $table->decimal('unit_price', 18, 2);
                $table->unsignedInteger('quantity');
                $table->decimal('line_total', 18, 2);

                $table->string('fulfilment_status', 30)
                    ->default('pending');

                $table->timestamp('confirmed_at')
                    ->nullable();
                $table->timestamp('shipped_at')
                    ->nullable();
                $table->timestamp('delivered_at')
                    ->nullable();
                $table->timestamp('cancelled_at')
                    ->nullable();

                $table->timestamps();

                $table->unique(
                    [
                        'product_order_id',
                        'product_variant_id',
                    ],
                    'order_variant_unique'
                );

                $table->index([
                    'seller_profile_id',
                    'fulfilment_status',
                ]);

                $table->index([
                    'product_order_id',
                    'fulfilment_status',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('product_order_items');
    }
};
