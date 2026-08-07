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
        Schema::table(
            'seller_profiles',
            function (Blueprint $table): void {
                /*
                 * Store branding
                 */
                $table
                    ->string('logo')
                    ->nullable()
                    ->after('trading_name');

                $table
                    ->string('cover_image')
                    ->nullable()
                    ->after('logo');

                /*
                 * Business classification
                 */
                $table
                    ->string('business_type', 50)
                    ->nullable()
                    ->after('cover_image');

                /*
                 * Additional contact
                 */
                $table
                    ->string('whatsapp', 30)
                    ->nullable()
                    ->after('business_phone');

                /*
                 * Store policies
                 */
                $table
                    ->text('return_policy')
                    ->nullable()
                    ->after('description');

                $table
                    ->text('warranty_policy')
                    ->nullable()
                    ->after('return_policy');

                /*
                 * Seller performance.
                 * These values are controlled by the system.
                 */
                $table
                    ->decimal(
                        'average_rating',
                        3,
                        2
                    )
                    ->default(0)
                    ->after('warranty_policy');

                $table
                    ->unsignedInteger('total_reviews')
                    ->default(0)
                    ->after('average_rating');

                $table
                    ->unsignedInteger('total_orders')
                    ->default(0)
                    ->after('total_reviews');

                $table
                    ->unsignedInteger('completed_orders')
                    ->default(0)
                    ->after('total_orders');

                $table
                    ->decimal(
                        'response_rate',
                        5,
                        2
                    )
                    ->default(0)
                    ->after('completed_orders');

                /*
                 * Response time stored in minutes.
                 */
                $table
                    ->unsignedInteger('response_time')
                    ->nullable()
                    ->after('response_rate');

                $table->index('business_type');
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(
            'seller_profiles',
            function (Blueprint $table): void {
                $table->dropIndex([
                    'business_type',
                ]);

                $table->dropColumn([
                    'logo',
                    'cover_image',
                    'business_type',
                    'whatsapp',
                    'return_policy',
                    'warranty_policy',
                    'average_rating',
                    'total_reviews',
                    'total_orders',
                    'completed_orders',
                    'response_rate',
                    'response_time',
                ]);
            }
        );
    }
};