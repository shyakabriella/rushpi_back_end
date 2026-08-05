<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add upload and image-detection metadata.
     */
    public function up(): void
    {
        if (!Schema::hasTable('product_media')) {
            return;
        }

        if (
            Schema::hasColumn(
                'product_media',
                'metadata'
            )
        ) {
            return;
        }

        Schema::table(
            'product_media',
            function (Blueprint $table): void {
                $table
                    ->json('metadata')
                    ->nullable();
            }
        );
    }

    /**
     * Remove product-media metadata.
     */
    public function down(): void
    {
        if (
            !Schema::hasTable('product_media')
            || !Schema::hasColumn(
                'product_media',
                'metadata'
            )
        ) {
            return;
        }

        Schema::table(
            'product_media',
            function (Blueprint $table): void {
                $table->dropColumn('metadata');
            }
        );
    }
};
