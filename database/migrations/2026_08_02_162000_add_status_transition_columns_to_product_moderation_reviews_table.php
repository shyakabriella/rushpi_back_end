<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add status-transition audit columns to moderation reviews.
     */
    public function up(): void
    {
        if (
            !Schema::hasTable(
                'product_moderation_reviews'
            )
        ) {
            return;
        }

        $hasFromStatus =
            Schema::hasColumn(
                'product_moderation_reviews',
                'from_status'
            );

        $hasToStatus =
            Schema::hasColumn(
                'product_moderation_reviews',
                'to_status'
            );

        if (
            $hasFromStatus
            && $hasToStatus
        ) {
            return;
        }

        Schema::table(
            'product_moderation_reviews',
            function (Blueprint $table) use (
                $hasFromStatus,
                $hasToStatus
            ): void {
                if (!$hasFromStatus) {
                    $table
                        ->string(
                            'from_status',
                            50
                        )
                        ->nullable();
                }

                if (!$hasToStatus) {
                    $table
                        ->string(
                            'to_status',
                            50
                        )
                        ->nullable();
                }
            }
        );
    }

    /**
     * Remove the transition audit columns.
     */
    public function down(): void
    {
        if (
            !Schema::hasTable(
                'product_moderation_reviews'
            )
        ) {
            return;
        }

        $columns = [];

        if (
            Schema::hasColumn(
                'product_moderation_reviews',
                'from_status'
            )
        ) {
            $columns[] =
                'from_status';
        }

        if (
            Schema::hasColumn(
                'product_moderation_reviews',
                'to_status'
            )
        ) {
            $columns[] =
                'to_status';
        }

        if ($columns === []) {
            return;
        }

        Schema::table(
            'product_moderation_reviews',
            function (Blueprint $table) use (
                $columns
            ): void {
                $table->dropColumn(
                    $columns
                );
            }
        );
    }
};
