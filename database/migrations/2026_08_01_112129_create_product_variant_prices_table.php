<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the current price record for each product variant.
     */
    public function up(): void
    {
        Schema::create(
            'product_variant_prices',
            function (Blueprint $table): void {
                $table->id();

                /*
                 * Each variant has only one current price record.
                 */
                $table->foreignId('product_variant_id')
                    ->unique()
                    ->constrained('product_variants')
                    ->cascadeOnDelete();

                /*
                 * ISO 4217 currency code.
                 * RushPi initially uses Rwandan francs.
                 */
                $table->char('currency', 3)
                    ->default('RWF');

                /*
                 * Current amount paid by the customer.
                 */
                $table->decimal('selling_price', 18, 2);

                /*
                 * Previous or normal price displayed when
                 * the product is being sold at a discount.
                 */
                $table->decimal('compare_at_price', 18, 2)
                    ->nullable();

                /*
                 * Seller acquisition cost.
                 * This field must never be exposed publicly.
                 */
                $table->decimal('cost_price', 18, 2)
                    ->nullable();

                $table->foreignId('created_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->foreignId('updated_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamps();

                $table->index([
                    'currency',
                    'selling_price',
                ]);
            }
        );
    }

    /**
     * Remove product variant prices.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variant_prices');
    }
};