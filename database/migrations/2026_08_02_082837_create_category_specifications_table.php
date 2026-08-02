<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Connect reusable specification definitions to product categories.
     *
     * This table allows every category to decide:
     *
     * - which specifications are available;
     * - which specifications are required;
     * - which specifications can be used as filters;
     * - which specifications create product variants;
     * - which category-specific validation rules apply.
     */
    public function up(): void
    {
        Schema::create(
            'category_specifications',
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
                | Taxonomy relationships
                |--------------------------------------------------------------------------
                */

                $table
                    ->foreignId('category_id')
                    ->constrained('categories')
                    ->cascadeOnUpdate()
                    ->cascadeOnDelete();

                $table
                    ->foreignId('specification_definition_id')
                    ->constrained('specification_definitions')
                    ->cascadeOnUpdate()
                    ->cascadeOnDelete();

                /*
                |--------------------------------------------------------------------------
                | Category-specific display configuration
                |--------------------------------------------------------------------------
                |
                | A custom label allows the same reusable definition to use
                | different wording in different categories.
                |
                | Example:
                |
                | Definition: storage_capacity
                | Laptop label: Storage capacity
                | Smartphone label: Internal storage
                |
                */

                $table
                    ->string('label', 150)
                    ->nullable();

                $table
                    ->text('help_text')
                    ->nullable();

                /*
                |--------------------------------------------------------------------------
                | Category-specific behavior
                |--------------------------------------------------------------------------
                */

                $table
                    ->boolean('is_required')
                    ->default(false);

                $table
                    ->boolean('is_filterable')
                    ->default(false);

                $table
                    ->boolean('is_variant_attribute')
                    ->default(false);

                $table
                    ->boolean('is_active')
                    ->default(true);

                /*
                |--------------------------------------------------------------------------
                | Category-specific validation overrides
                |--------------------------------------------------------------------------
                |
                | These rules override or extend the reusable definition rules.
                |
                | Example:
                |
                | {
                |     "min": 4,
                |     "max": 128,
                |     "step": 4
                | }
                |
                */

                $table
                    ->json('validation_rules')
                    ->nullable();

                /*
                |--------------------------------------------------------------------------
                | Category-specific selectable options
                |--------------------------------------------------------------------------
                |
                | This can restrict a global specification to options allowed
                | for one particular category.
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
                | Category-specific default value
                |--------------------------------------------------------------------------
                */

                $table
                    ->json('default_value')
                    ->nullable();

                /*
                |--------------------------------------------------------------------------
                | Display order
                |--------------------------------------------------------------------------
                */

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

                /*
                |--------------------------------------------------------------------------
                | Constraints
                |--------------------------------------------------------------------------
                |
                | One specification definition can only be assigned once to
                | the same category.
                |
                */

                $table->unique(
                    [
                        'category_id',
                        'specification_definition_id',
                    ],
                    'category_specification_unique'
                );

                /*
                |--------------------------------------------------------------------------
                | Query indexes
                |--------------------------------------------------------------------------
                */

                $table->index('is_required');
                $table->index('is_filterable');
                $table->index('is_variant_attribute');
                $table->index('is_active');
                $table->index('sort_order');

                $table->index(
                    [
                        'category_id',
                        'is_active',
                        'sort_order',
                    ],
                    'category_specification_listing_index'
                );

                $table->index(
                    [
                        'category_id',
                        'is_required',
                        'is_active',
                    ],
                    'category_required_specification_index'
                );

                $table->index(
                    [
                        'category_id',
                        'is_filterable',
                        'is_active',
                    ],
                    'category_filterable_specification_index'
                );

                $table->index(
                    [
                        'category_id',
                        'is_variant_attribute',
                        'is_active',
                    ],
                    'category_variant_specification_index'
                );
            }
        );
    }

    /**
     * Remove category specification assignments.
     */
    public function down(): void
    {
        Schema::dropIfExists(
            'category_specifications'
        );
    }
};