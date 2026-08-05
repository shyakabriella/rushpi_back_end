<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ensure the moderation review table contains every audit field
     * required by the current moderation workflow.
     */
    public function up(): void
    {
        $tableName =
            'product_moderation_reviews';

        if (!Schema::hasTable($tableName)) {
            return;
        }

        $missing = [
            'from_status' =>
                !Schema::hasColumn(
                    $tableName,
                    'from_status'
                ),

            'to_status' =>
                !Schema::hasColumn(
                    $tableName,
                    'to_status'
                ),

            'notes' =>
                !Schema::hasColumn(
                    $tableName,
                    'notes'
                ),

            'moderation_flags' =>
                !Schema::hasColumn(
                    $tableName,
                    'moderation_flags'
                ),

            'is_prohibited_item' =>
                !Schema::hasColumn(
                    $tableName,
                    'is_prohibited_item'
                ),

            'flag_notes' =>
                !Schema::hasColumn(
                    $tableName,
                    'flag_notes'
                ),

            'flagged_at' =>
                !Schema::hasColumn(
                    $tableName,
                    'flagged_at'
                ),

            'metadata' =>
                !Schema::hasColumn(
                    $tableName,
                    'metadata'
                ),
        ];

        if (!in_array(true, $missing, true)) {
            return;
        }

        Schema::table(
            $tableName,
            function (Blueprint $table) use ($missing): void {
                if ($missing['from_status']) {
                    $table
                        ->string(
                            'from_status',
                            50
                        )
                        ->nullable();
                }

                if ($missing['to_status']) {
                    $table
                        ->string(
                            'to_status',
                            50
                        )
                        ->nullable();
                }

                if ($missing['notes']) {
                    $table
                        ->text('notes')
                        ->nullable();
                }

                if ($missing['moderation_flags']) {
                    $table
                        ->json('moderation_flags')
                        ->nullable();
                }

                if ($missing['is_prohibited_item']) {
                    $table
                        ->boolean(
                            'is_prohibited_item'
                        )
                        ->default(false);
                }

                if ($missing['flag_notes']) {
                    $table
                        ->text('flag_notes')
                        ->nullable();
                }

                if ($missing['flagged_at']) {
                    $table
                        ->timestamp('flagged_at')
                        ->nullable();
                }

                if ($missing['metadata']) {
                    $table
                        ->json('metadata')
                        ->nullable();
                }
            }
        );
    }

    /**
     * This is a compatibility migration.
     *
     * The down operation intentionally leaves these columns in place because
     * some installations may already have had individual columns before this
     * migration was executed. Removing them could destroy existing audit data.
     */
    public function down(): void
    {
        // Intentionally left empty.
    }
};
