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
            'user_profiles',
            function (Blueprint $table): void {
                $table->id();

                /*
                 * Every user has at most one
                 * personal profile.
                 */
                $table
                    ->foreignId('user_id')
                    ->unique()
                    ->constrained('users')
                    ->cascadeOnDelete();

                /*
                 * Personal identity.
                 *
                 * The main name remains in users.name.
                 * These optional fields provide more
                 * structured profile information.
                 */
                $table
                    ->string('first_name')
                    ->nullable();

                $table
                    ->string('last_name')
                    ->nullable();

                $table
                    ->string('display_name')
                    ->nullable();

                /*
                 * Personal profile image.
                 */
                $table
                    ->string('avatar')
                    ->nullable();

                /*
                 * Personal description.
                 */
                $table
                    ->text('bio')
                    ->nullable();

                /*
                 * Optional personal information.
                 */
                $table
                    ->string('gender', 30)
                    ->nullable();

                $table
                    ->date('date_of_birth')
                    ->nullable();

                /*
                 * Additional contact information.
                 *
                 * Main phone remains available on users.phone.
                 */
                $table
                    ->string('alternative_phone', 30)
                    ->nullable();

                $table
                    ->string('whatsapp', 30)
                    ->nullable();

                /*
                 * Location.
                 */
                $table
                    ->string('country', 100)
                    ->nullable();

                $table
                    ->string('province', 100)
                    ->nullable();

                $table
                    ->string('district', 100)
                    ->nullable();

                $table
                    ->string('sector', 100)
                    ->nullable();

                $table
                    ->string('cell', 100)
                    ->nullable();

                $table
                    ->string('village', 100)
                    ->nullable();

                $table
                    ->text('address')
                    ->nullable();

                /*
                 * Optional geographic coordinates.
                 */
                $table
                    ->decimal(
                        'latitude',
                        10,
                        7
                    )
                    ->nullable();

                $table
                    ->decimal(
                        'longitude',
                        10,
                        7
                    )
                    ->nullable();

                /*
                 * Profile completion can be calculated,
                 * but storing a timestamp for when the
                 * user considers the profile completed
                 * can be useful.
                 */
                $table
                    ->timestamp('completed_at')
                    ->nullable();

                $table->timestamps();

                /*
                 * Useful indexes.
                 */
                $table->index([
                    'country',
                    'province',
                ]);

                $table->index([
                    'district',
                    'sector',
                ]);
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists(
            'user_profiles'
        );
    }
};