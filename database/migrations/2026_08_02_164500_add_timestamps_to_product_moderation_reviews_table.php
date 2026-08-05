<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add Laravel timestamps required by the moderation review model.
     */
    public function up(): void
    {
        $tableName = 'product_moderation_reviews';

        if (!Schema::hasTable($tableName)) {
            return;
        }

        $addCreatedAt = !Schema::hasColumn(
            $tableName,
            'created_at'
        );

        $addUpdatedAt = !Schema::hasColumn(
            $tableName,
            'updated_at'
        );

        if (!$addCreatedAt && !$addUpdatedAt) {
            return;
        }

        Schema::table(
            $tableName,
            function (Blueprint $table) use (
                $addCreatedAt,
                $addUpdatedAt
            ): void {
                if ($addCreatedAt) {
                    $table
                        ->timestamp('created_at')
                        ->nullable();
                }

                if ($addUpdatedAt) {
                    $table
                        ->timestamp('updated_at')
                        ->nullable();
                }
            }
        );
    }

    /**
     * Keep existing audit timestamps during rollback.
     *
     * This compatibility migration intentionally does not remove columns
     * because another installation may already rely on these audit values.
     */
    public function down(): void
    {
        // Intentionally left empty.
    }
};
