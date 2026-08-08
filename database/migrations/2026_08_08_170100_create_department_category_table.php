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
            'department_category',
            function (Blueprint $table): void {
                $table->id();

                $table
                    ->foreignId(
                        'department_id'
                    )
                    ->constrained(
                        'departments'
                    )
                    ->cascadeOnDelete();

                $table
                    ->foreignId(
                        'category_id'
                    )
                    ->constrained(
                        'categories'
                    )
                    ->cascadeOnDelete();

                /*
                 * One category belongs to one
                 * marketplace department.
                 */
                $table->unique(
                    'category_id'
                );

                $table
                    ->unsignedInteger(
                        'sort_order'
                    )
                    ->default(0);

                $table
                    ->boolean(
                        'is_featured'
                    )
                    ->default(false);

                $table
                    ->boolean(
                        'is_active'
                    )
                    ->default(true);

                $table->timestamps();

                $table->index([
                    'department_id',
                    'is_active',
                    'sort_order',
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'department_category'
        );
    }
};