<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create the brand-category assignment table.
     *
     * A brand may be available in many categories,
     * and a category may contain many brands.
     *
     * Examples:
     *
     * Samsung
     * - Smartphones
     * - Televisions
     * - Refrigerators
     *
     * Nike
     * - Sneakers
     * - Sportswear
     * - Accessories
     */
    public function up(): void
    {
        Schema::create(
            'brand_category',
            function (Blueprint $table): void {
                $table->id();

                /*
                 * Brand being assigned.
                 */
                $table
                    ->foreignId('brand_id')
                    ->constrained('brands')
                    ->cascadeOnDelete();

                /*
                 * Existing saved category selected
                 * by the administrator.
                 */
                $table
                    ->foreignId('category_id')
                    ->constrained('categories')
                    ->cascadeOnDelete();

                /*
                 * Allows an assignment to be disabled
                 * without removing it completely.
                 */
                $table
                    ->boolean('is_active')
                    ->default(true);

                /*
                 * Optional ordering of brands
                 * inside a category.
                 */
                $table
                    ->unsignedInteger('sort_order')
                    ->default(0);

                $table->timestamps();

                /*
                 * Prevent the same brand from being
                 * assigned twice to the same category.
                 */
                $table->unique(
                    [
                        'brand_id',
                        'category_id',
                    ],
                    'brand_category_unique'
                );

                /*
                 * Helpful when retrieving brands
                 * for a selected category.
                 */
                $table->index(
                    [
                        'category_id',
                        'is_active',
                        'sort_order',
                    ],
                    'brand_category_category_index'
                );

                /*
                 * Helpful when loading all categories
                 * assigned to a brand.
                 */
                $table->index(
                    [
                        'brand_id',
                        'is_active',
                    ],
                    'brand_category_brand_index'
                );
            }
        );
    }

    /**
     * Remove the brand-category assignment table.
     */
    public function down(): void
    {
        Schema::dropIfExists(
            'brand_category'
        );
    }
};