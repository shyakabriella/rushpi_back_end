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
            'commission_rules',
            function (Blueprint $table): void {
                $table->id();

                /*
                 * Public identifier used by the API.
                 */
                $table
                    ->ulid('public_id')
                    ->unique();

                /*
                 * Human-readable name.
                 *
                 * Examples:
                 * - Default Marketplace Commission
                 * - Electronics Commission
                 * - Laptop Commission
                 */
                $table->string(
                    'name',
                    150
                );

                /*
                 * Commission scope:
                 *
                 * global
                 * department
                 * category
                 */
                $table->string(
                    'scope',
                    30
                );

                /*
                 * Department target.
                 *
                 * Used when:
                 * scope = department
                 */
                $table
                    ->foreignId(
                        'department_id'
                    )
                    ->nullable()
                    ->constrained(
                        'departments'
                    )
                    ->nullOnDelete();

                /*
                 * Category target.
                 *
                 * Used when:
                 * scope = category
                 */
                $table
                    ->foreignId(
                        'category_id'
                    )
                    ->nullable()
                    ->constrained(
                        'categories'
                    )
                    ->nullOnDelete();

                /*
                 * Commission type:
                 *
                 * percentage
                 * fixed
                 */
                $table->string(
                    'commission_type',
                    30
                );

                /*
                 * Examples:
                 *
                 * Percentage:
                 * 6.0000 = 6%
                 *
                 * Fixed:
                 * 5000.0000 = 5,000 RWF
                 */
                $table->decimal(
                    'commission_value',
                    15,
                    4
                );

                /*
                 * Optional minimum commission.
                 *
                 * Example:
                 * 5% commission but at least 1,000 RWF.
                 */
                $table
                    ->decimal(
                        'minimum_commission',
                        15,
                        2
                    )
                    ->nullable();

                /*
                 * Optional maximum commission.
                 *
                 * Example:
                 * 5% commission but never more
                 * than 100,000 RWF.
                 */
                $table
                    ->decimal(
                        'maximum_commission',
                        15,
                        2
                    )
                    ->nullable();

                /*
                 * Currency used for fixed/min/max values.
                 */
                $table
                    ->char(
                        'currency',
                        3
                    )
                    ->default('RWF');

                /*
                 * Used when multiple rules exist
                 * at the same scope.
                 *
                 * Higher priority wins.
                 */
                $table
                    ->unsignedInteger(
                        'priority'
                    )
                    ->default(0);

                /*
                 * Optional start date.
                 */
                $table
                    ->timestamp(
                        'starts_at'
                    )
                    ->nullable();

                /*
                 * Optional expiry date.
                 */
                $table
                    ->timestamp(
                        'ends_at'
                    )
                    ->nullable();

                /*
                 * Enable / disable rule.
                 */
                $table
                    ->boolean(
                        'is_active'
                    )
                    ->default(true);

                /*
                 * Administrator who created the rule.
                 */
                $table
                    ->foreignId(
                        'created_by'
                    )
                    ->nullable()
                    ->constrained(
                        'users'
                    )
                    ->nullOnDelete();

                /*
                 * Administrator who last updated the rule.
                 */
                $table
                    ->foreignId(
                        'updated_by'
                    )
                    ->nullable()
                    ->constrained(
                        'users'
                    )
                    ->nullOnDelete();

                $table->timestamps();

                /*
                 * Keep historical rule records
                 * recoverable.
                 */
                $table->softDeletes();

                /*
                 * Helpful when resolving active
                 * commission rules.
                 */
                $table->index(
                    [
                        'scope',
                        'is_active',
                        'priority',
                    ],
                    'commission_rules_scope_index'
                );

                /*
                 * Department commission lookup.
                 */
                $table->index(
                    [
                        'department_id',
                        'is_active',
                    ],
                    'commission_rules_department_index'
                );

                /*
                 * Category commission lookup.
                 */
                $table->index(
                    [
                        'category_id',
                        'is_active',
                    ],
                    'commission_rules_category_index'
                );

                /*
                 * Effective date lookup.
                 */
                $table->index(
                    [
                        'starts_at',
                        'ends_at',
                    ],
                    'commission_rules_period_index'
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
            'commission_rules'
        );
    }
};