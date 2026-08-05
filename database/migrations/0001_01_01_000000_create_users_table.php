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
            'users',
            function (Blueprint $table): void {
                $table->id();

                /*
                 * Basic user information
                 */
                $table->string('name');

                $table
                    ->string('email')
                    ->unique();

                $table
                    ->string('phone', 30)
                    ->nullable()
                    ->unique();

                /*
                 * Authentication
                 */
                $table
                    ->timestamp('email_verified_at')
                    ->nullable();

                $table->string('password');

                /*
                 * RushPi account role
                 *
                 * admin:
                 * Manages all activities and settings in the system.
                 *
                 * seller:
                 * A shop owner or individual who owns products to sell.
                 *
                 * dealer:
                 * A person who creates deals or connects buyers and sellers.
                 *
                 * commissioner:
                 * A commission agent who sells products for a shop
                 * and earns commission.
                 *
                 * No default is used. Every account creation process
                 * must explicitly assign a role.
                 */
                $table->enum(
                    'role',
                    [
                        'admin',
                        'seller',
                        'dealer',
                        'commissioner',
                    ]
                );

                /*
                 * Account status
                 */
                $table
                    ->enum(
                        'status',
                        [
                            'active',
                            'inactive',
                            'blocked',
                        ]
                    )
                    ->default('active');

                /*
                 * Optional profile information
                 */
                $table
                    ->string('avatar')
                    ->nullable();

                $table
                    ->text('address')
                    ->nullable();

                $table->rememberToken();
                $table->timestamps();

                /*
                 * Helpful filtering indexes
                 */
                $table->index('role');
                $table->index('status');

                $table->index([
                    'role',
                    'status',
                ]);
            }
        );

        Schema::create(
            'password_reset_tokens',
            function (Blueprint $table): void {
                $table
                    ->string('email')
                    ->primary();

                $table->string('token');

                $table
                    ->timestamp('created_at')
                    ->nullable();
            }
        );

        Schema::create(
            'sessions',
            function (Blueprint $table): void {
                $table
                    ->string('id')
                    ->primary();

                $table
                    ->foreignId('user_id')
                    ->nullable()
                    ->index();

                $table
                    ->string('ip_address', 45)
                    ->nullable();

                $table
                    ->text('user_agent')
                    ->nullable();

                $table->longText('payload');

                $table
                    ->integer('last_activity')
                    ->index();
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');

        Schema::dropIfExists(
            'password_reset_tokens'
        );

        Schema::dropIfExists('users');
    }
};
