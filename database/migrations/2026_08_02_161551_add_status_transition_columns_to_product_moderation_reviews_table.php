<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the status transition recorded by each moderation decision.
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

        $addFromStatus =
            !Schema::hasColumn(
                'product_moderation_reviews',
                'from_status'
            );

        $addToStatus =
            !Schema::hasColumn(
                'product_moderation_reviews',
                'to_status'
            );

        if (
            !$addFromStatus
            && !$addToStatus
        ) {
            return;
        }

        Schema::table(
            'product_moderation_reviews',
            function (Blueprint $table) use (
                $addFromStatus,
                $addToStatus
            ): void {
                if ($addFromStatus) {
                    $table
                        ->string(
                            'from_status',
                            50
                        )
                        ->nullable();
                }

                if ($addToStatus) {
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
     * Remove moderation transition columns.
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