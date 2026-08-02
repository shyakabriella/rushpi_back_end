<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            /*
            |--------------------------------------------------------------------------
            | Authentication and authorization
            |--------------------------------------------------------------------------
            */

            RoleSeeder::class,
            UserSeeder::class,

            /*
            |--------------------------------------------------------------------------
            | Product specification catalog
            |--------------------------------------------------------------------------
            |
            | Definitions must be created before they can be assigned to
            | product categories.
            |
            */

            SpecificationDefinitionSeeder::class,
            CategorySpecificationSeeder::class,
        ]);
    }
}