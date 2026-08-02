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
            'product_return_policies',
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
                | Product relationship
                |--------------------------------------------------------------------------
                |
                | Each product has at most one return policy.
                |
                */

                $table
                    ->foreignId('product_id')
                    ->constrained('products')
                    ->cascadeOnDelete();

                $table->unique(
                    'product_id',
                    'product_return_policies_product_unique'
                );

                /*
                |--------------------------------------------------------------------------
                | Return eligibility
                |--------------------------------------------------------------------------
                */

                $table
                    ->boolean('is_returnable')
                    ->default(true);

                $table
                    ->unsignedSmallInteger(
                        'return_window_days'
                    )
                    ->nullable()
                    ->default(7);

                /*
                |--------------------------------------------------------------------------
                | Available resolutions
                |--------------------------------------------------------------------------
                */

                $table
                    ->boolean('allow_refund')
                    ->default(true);

                $table
                    ->boolean('allow_exchange')
                    ->default(true);

                /*
                |--------------------------------------------------------------------------
                | Return requirements
                |--------------------------------------------------------------------------
                */

                $table
                    ->boolean(
                        'requires_original_packaging'
                    )
                    ->default(true);

                $table
                    ->boolean(
                        'requires_proof_of_purchase'
                    )
                    ->default(true);

                /*
                |--------------------------------------------------------------------------
                | Financial configuration
                |--------------------------------------------------------------------------
                */

                $table
                    ->decimal(
                        'restocking_fee_percent',
                        5,
                        2
                    )
                    ->default(0);

                /*
                |--------------------------------------------------------------------------
                | Shipping responsibility
                |--------------------------------------------------------------------------
                |
                | Supported application values:
                |
                | customer
                | seller
                | platform
                | conditional
                |
                */

                $table
                    ->string(
                        'return_shipping_payer',
                        30
                    )
                    ->default('customer');

                /*
                |--------------------------------------------------------------------------
                | Structured configuration
                |--------------------------------------------------------------------------
                |
                | accepted_conditions example:
                |
                | [
                |     "unused",
                |     "unopened",
                |     "defective",
                |     "wrong_item"
                | ]
                |
                | refund_methods example:
                |
                | [
                |     "original_payment_method",
                |     "wallet_credit",
                |     "bank_transfer"
                | ]
                |
                */

                $table
                    ->json('accepted_conditions')
                    ->nullable();

                $table
                    ->json('refund_methods')
                    ->nullable();

                /*
                |--------------------------------------------------------------------------
                | Customer-facing policy information
                |--------------------------------------------------------------------------
                */

                $table
                    ->text('instructions')
                    ->nullable();

                $table
                    ->text('non_returnable_reason')
                    ->nullable();

                /*
                |--------------------------------------------------------------------------
                | Availability
                |--------------------------------------------------------------------------
                */

                $table
                    ->boolean('is_active')
                    ->default(true);

                /*
                |--------------------------------------------------------------------------
                | Audit information
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
                | Indexes
                |--------------------------------------------------------------------------
                */

                $table->index(
                    [
                        'is_active',
                        'is_returnable',
                    ],
                    'product_return_policies_availability_index'
                );

                $table->index(
                    'return_shipping_payer',
                    'product_return_policies_shipping_index'
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
            'product_return_policies'
        );
    }
};