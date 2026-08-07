<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create(
            'seller_document_requirements',
            function (Blueprint $table): void {
                $table->id();

                /*
                 * Machine-readable identifier used by
                 * frontend and seller_documents.document_type.
                 */
                $table
                    ->string('key', 100)
                    ->unique();

                /*
                 * Human-readable document name.
                 */
                $table
                    ->string('name', 255);

                /*
                 * required
                 * conditional
                 * recommended
                 */
                $table->enum(
                    'requirement_level',
                    [
                        'required',
                        'conditional',
                        'recommended',
                    ]
                );

                /*
                 * Explains when a conditional
                 * document becomes required.
                 */
                $table
                    ->string('condition')
                    ->nullable();

                /*
                 * Explanation displayed to seller/admin.
                 */
                $table
                    ->text('description')
                    ->nullable();

                /*
                 * Some document categories may accept
                 * multiple supporting files.
                 */
                $table
                    ->boolean('allow_multiple')
                    ->default(false);

                /*
                 * Some documents can contain an expiry date.
                 */
                $table
                    ->boolean('supports_expiry_date')
                    ->default(false);

                /*
                 * Allows administrators to disable
                 * a requirement without deleting history.
                 */
                $table
                    ->boolean('is_active')
                    ->default(true);

                /*
                 * Display order.
                 */
                $table
                    ->unsignedSmallInteger('sort_order')
                    ->default(0);

                $table->timestamps();

                $table->index([
                    'requirement_level',
                    'is_active',
                ]);

                $table->index(
                    'sort_order'
                );
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(
            'seller_document_requirements'
        );
    }
};