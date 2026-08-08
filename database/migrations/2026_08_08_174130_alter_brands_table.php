<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add marketplace query indexes to the existing brands table.
     */
    public function up(): void
    {
        Schema::table(
            'brands',
            function (Blueprint $table): void {
                /*
                 * Useful when brands are ordered independently
                 * by their administrative sort position.
                 */
                $table->index(
                    'sort_order',
                    'brands_sort_order_index'
                );

                /*
                 * Helps queries involving active/soft-deleted
                 * marketplace brands.
                 */
                $table->index(
                    'deleted_at',
                    'brands_deleted_at_index'
                );
            }
        );
    }

    /**
     * Reverse the changes.
     */
    public function down(): void
    {
        Schema::table(
            'brands',
            function (Blueprint $table): void {
                $table->dropIndex(
                    'brands_sort_order_index'
                );

                $table->dropIndex(
                    'brands_deleted_at_index'
                );
            }
        );
    }
};