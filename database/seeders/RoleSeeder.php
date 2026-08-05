<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Seed the available system roles.
     */
    public function run(): void
    {
        /*
         * Clear Spatie's cached roles and permissions before
         * creating or updating the roles.
         */
        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        $roles = [
            'admin',
            'seller',
            'dealer',
            'commissioner',
        ];

        DB::transaction(
            function () use ($roles): void {
                foreach ($roles as $roleName) {
                    Role::findOrCreate(
                        $roleName,
                        'web'
                    );
                }
            }
        );

        /*
         * Clear the cache again so the new roles become
         * available immediately.
         */
        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();
    }
}
