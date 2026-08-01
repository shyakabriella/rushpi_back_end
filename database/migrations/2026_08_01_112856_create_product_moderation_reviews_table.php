<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create product moderation history.
     */
    public function up(): void
    {
        Schema::create(
            'product_moderation_reviews',
            function (Blueprint $table): void {
                $table->id();

                $table->ulid('public_id')->unique();

                $table->foreignId('product_id')
                    ->constrained('products')
                    ->cascadeOnDelete();

                /*
                 * Administrator who performed the review action.
                 *
                 * This may be null for a seller submission action.
                 */
                $table->foreignId('reviewed_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                /*
                 * Examples:
                 * submitted
                 * review_started
                 * approved
                 * rejected
                 * suspended
                 * restored
                 */
                $table->string('action', 50);

                /*
                 * Public-facing reason that may be shown
                 * to the seller.
                 */
                $table->text('reason')
                    ->nullable();

                /*
                 * Administrator-only notes.
                 * These must never appear in public APIs.
                 */
                $table->text('internal_notes')
                    ->nullable();

                /*
                 * Snapshot of the important product information
                 * at the time this moderation action occurred.
                 */
                $table->json('product_snapshot')
                    ->nullable();

                $table->timestamp('created_at')
                    ->useCurrent();

                $table->index([
                    'product_id',
                    'created_at',
                ]);

                $table->index([
                    'product_id',
                    'action',
                ]);

                $table->index([
                    'reviewed_by',
                    'created_at',
                ]);

                $table->index([
                    'action',
                    'created_at',
                ]);
            }
        );
    }

    /**
     * Remove product moderation history.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_moderation_reviews');
    }
};