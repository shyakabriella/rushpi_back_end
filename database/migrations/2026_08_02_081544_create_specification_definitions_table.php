<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create reusable catalog specification definitions.
     *
     * Examples:
     * - RAM
     * - Storage
     * - Processor
     * - Screen size
     * - Operating system
     * - Battery capacity
     */
    public function up(): void
    {
        Schema::create(
            'specification_definitions',
            function (Blueprint $table): void {
                $table->id();

                /*
                |--------------------------------------------------------------------------
                | Public identifier
                |--------------------------------------------------------------------------
                */

                $table
                    ->char('public_id', 26)
                    ->unique();

                /*
                |--------------------------------------------------------------------------
                | Definition identity
                |--------------------------------------------------------------------------
                |
                | The code is a stable machine-readable identifier.
                |
                | Examples:
                | ram
                | storage_capacity
                | processor
                | screen_size
                |
                */

                $table
                    ->string('name', 150);

                $table
                    ->string('code', 150)
                    ->unique();

                $table
                    ->text('description')
                    ->nullable();

                /*
                |--------------------------------------------------------------------------
                | Typed value configuration
                |--------------------------------------------------------------------------
                |
                | Supported initial values:
                |
                | text
                | integer
                | decimal
                | boolean
                | select
                | multiselect
                | date
                |
                | The application enum and request validation will enforce
                | the supported types later.
                |
                */

                $table
                    ->string('data_type', 30)
                    ->default('text');

                /*
                |--------------------------------------------------------------------------
                | Optional measurement unit
                |--------------------------------------------------------------------------
                |
                | Examples:
                | GB
                | TB
                | inch
                | GHz
                | mAh
                | months
                |
                */

                $table
                    ->string('unit', 50)
                    ->nullable();

                /*
                |--------------------------------------------------------------------------
                | Selectable options
                |--------------------------------------------------------------------------
                |
                | Used mainly for select and multiselect definitions.
                |
                | Example:
                |
                | [
                |     {"value": "8", "label": "8 GB"},
                |     {"value": "16", "label": "16 GB"},
                |     {"value": "32", "label": "32 GB"}
                | ]
                |
                */

                $table
                    ->json('options')
                    ->nullable();

                /*
                |--------------------------------------------------------------------------
                | Dynamic validation configuration
                |--------------------------------------------------------------------------
                |
                | Example:
                |
                | {
                |     "min": 1,
                |     "max": 128,
                |     "step": 1
                | }
                |
                */

                $table
                    ->json('validation_rules')
                    ->nullable();

                /*
                |--------------------------------------------------------------------------
                | Optional default value
                |--------------------------------------------------------------------------
                |
                | JSON allows the default to support text, numbers,
                | booleans, arrays and other typed values.
                |
                */

                $table
                    ->json('default_value')
                    ->nullable();

                /*
                |--------------------------------------------------------------------------
                | Catalog behavior
                |--------------------------------------------------------------------------
                */

                $table
                    ->boolean('is_filterable')
                    ->default(false);

                $table
                    ->boolean('is_variant_attribute')
                    ->default(false);

                $table
                    ->boolean('is_active')
                    ->default(true);

                $table
                    ->unsignedInteger('sort_order')
                    ->default(0);

                /*
                |--------------------------------------------------------------------------
                | Audit users
                |--------------------------------------------------------------------------
                */

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

                /*
                |--------------------------------------------------------------------------
                | Query indexes
                |--------------------------------------------------------------------------
                */

                $table->index('data_type');
                $table->index('is_active');
                $table->index('is_filterable');
                $table->index('is_variant_attribute');
                $table->index('sort_order');

                $table->index([
                    'is_active',
                    'is_filterable',
                ]);

                $table->index([
                    'is_active',
                    'is_variant_attribute',
                ]);
            }
        );
    }

    /**
     * Remove reusable catalog specification definitions.
     */
    public function down(): void
    {
        Schema::dropIfExists(
            'specification_definitions'
        );
    }
};