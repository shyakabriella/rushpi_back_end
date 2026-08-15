<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasColumn(
                'services',
                'colors'
            )
        ) {
            Schema::table(
                'services',
                function (
                    Blueprint $table
                ): void {
                    $table->json(
                        'colors'
                    )
                        ->nullable()
                        ->after(
                            'color_name'
                        );
                }
            );
        }

        /*
         * Convert old single colors into
         * the new colors array.
         */
        DB::table('services')
            ->whereNotNull(
                'color_name'
            )
            ->where(
                'color_name',
                '<>',
                ''
            )
            ->whereNull(
                'colors'
            )
            ->orderBy('id')
            ->eachById(
                function (
                    object $service
                ): void {
                    DB::table(
                        'services'
                    )
                        ->where(
                            'id',
                            $service->id
                        )
                        ->update([
                            'colors' =>
                                json_encode([
                                    [
                                        'name' =>
                                            $service
                                                ->color_name,

                                        'hex' =>
                                            null,
                                    ],
                                ]),
                        ]);
                }
            );
    }

    public function down(): void
    {
        if (
            Schema::hasColumn(
                'services',
                'colors'
            )
        ) {
            Schema::table(
                'services',
                function (
                    Blueprint $table
                ): void {
                    $table->dropColumn(
                        'colors'
                    );
                }
            );
        }
    }
};
