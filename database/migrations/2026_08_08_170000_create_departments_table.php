<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'departments',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->ulid('public_id')
                    ->unique();

                $table
                    ->string('name', 150);

                $table
                    ->string('slug', 180)
                    ->unique();

                $table
                    ->text('description')
                    ->nullable();

                $table
                    ->string(
                        'image_path',
                        1000
                    )
                    ->nullable();

                $table
                    ->boolean('is_active')
                    ->default(true);

                $table
                    ->unsignedInteger(
                        'sort_order'
                    )
                    ->default(0);

                $table
                    ->foreignId('created_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table
                    ->foreignId('updated_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->timestamps();

                $table->softDeletes();

                $table->index([
                    'is_active',
                    'sort_order',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'departments'
        );
    }
};