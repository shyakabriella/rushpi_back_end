<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add structured moderation flags to product review history.
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

        $addModerationFlags =
            !Schema::hasColumn(
                'product_moderation_reviews',
                'moderation_flags'
            );

        $addIsProhibitedItem =
            !Schema::hasColumn(
                'product_moderation_reviews',
                'is_prohibited_item'
            );

        $addFlagNotes =
            !Schema::hasColumn(
                'product_moderation_reviews',
                'flag_notes'
            );

        $addFlaggedAt =
            !Schema::hasColumn(
                'product_moderation_reviews',
                'flagged_at'
            );

        if (
            !$addModerationFlags
            && !$addIsProhibitedItem
            && !$addFlagNotes
            && !$addFlaggedAt
        ) {
            return;
        }

        Schema::table(
            'product_moderation_reviews',
            function (Blueprint $table) use (
                $addModerationFlags,
                $addIsProhibitedItem,
                $addFlagNotes,
                $addFlaggedAt
            ): void {
                /*
                |--------------------------------------------------------------------------
                | Structured moderation flags
                |--------------------------------------------------------------------------
                |
                | Example:
                |
                | [
                |   "counterfeit_goods",
                |   "misleading_information"
                | ]
                |
                */

                if ($addModerationFlags) {
                    $table->json(
                        'moderation_flags'
                    )
                        ->nullable()
                        ->after('reason');
                }

                /*
                |--------------------------------------------------------------------------
                | Prohibited-item classification
                |--------------------------------------------------------------------------
                */

                if ($addIsProhibitedItem) {
                    $table->boolean(
                        'is_prohibited_item'
                    )
                        ->default(false)
                        ->after(
                            $addModerationFlags
                                ? 'moderation_flags'
                                : 'reason'
                        );
                }

                /*
                |--------------------------------------------------------------------------
                | Moderator notes related to flags
                |--------------------------------------------------------------------------
                */

                if ($addFlagNotes) {
                    $table->text(
                        'flag_notes'
                    )
                        ->nullable()
                        ->after(
                            $addIsProhibitedItem
                                ? 'is_prohibited_item'
                                : (
                                    $addModerationFlags
                                        ? 'moderation_flags'
                                        : 'reason'
                                )
                        );
                }

                /*
                |--------------------------------------------------------------------------
                | Flag timestamp
                |--------------------------------------------------------------------------
                */

                if ($addFlaggedAt) {
                    $table->timestamp(
                        'flagged_at'
                    )
                        ->nullable()
                        ->after(
                            $addFlagNotes
                                ? 'flag_notes'
                                : (
                                    $addIsProhibitedItem
                                        ? 'is_prohibited_item'
                                        : (
                                            $addModerationFlags
                                                ? 'moderation_flags'
                                                : 'reason'
                                        )
                                )
                        );
                }
            }
        );

        if (
            $addIsProhibitedItem
            && !Schema::hasIndex(
                'product_moderation_reviews',
                'product_moderation_reviews_prohibited_index'
            )
        ) {
            Schema::table(
                'product_moderation_reviews',
                function (Blueprint $table): void {
                    $table->index(
                        'is_prohibited_item',
                        'product_moderation_reviews_prohibited_index'
                    );
                }
            );
        }

        if (
            $addFlaggedAt
            && !Schema::hasIndex(
                'product_moderation_reviews',
                'product_moderation_reviews_flagged_at_index'
            )
        ) {
            Schema::table(
                'product_moderation_reviews',
                function (Blueprint $table): void {
                    $table->index(
                        'flagged_at',
                        'product_moderation_reviews_flagged_at_index'
                    );
                }
            );
        }
    }

    /**
     * Remove structured moderation flag fields.
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

        if (
            Schema::hasIndex(
                'product_moderation_reviews',
                'product_moderation_reviews_prohibited_index'
            )
        ) {
            Schema::table(
                'product_moderation_reviews',
                function (Blueprint $table): void {
                    $table->dropIndex(
                        'product_moderation_reviews_prohibited_index'
                    );
                }
            );
        }

        if (
            Schema::hasIndex(
                'product_moderation_reviews',
                'product_moderation_reviews_flagged_at_index'
            )
        ) {
            Schema::table(
                'product_moderation_reviews',
                function (Blueprint $table): void {
                    $table->dropIndex(
                        'product_moderation_reviews_flagged_at_index'
                    );
                }
            );
        }

        $columns = array_values(
            array_filter([
                Schema::hasColumn(
                    'product_moderation_reviews',
                    'moderation_flags'
                )
                    ? 'moderation_flags'
                    : null,

                Schema::hasColumn(
                    'product_moderation_reviews',
                    'is_prohibited_item'
                )
                    ? 'is_prohibited_item'
                    : null,

                Schema::hasColumn(
                    'product_moderation_reviews',
                    'flag_notes'
                )
                    ? 'flag_notes'
                    : null,

                Schema::hasColumn(
                    'product_moderation_reviews',
                    'flagged_at'
                )
                    ? 'flagged_at'
                    : null,
            ])
        );

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