<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
<<<<<<< HEAD
use Illuminate\Support\Facades\DB;
=======
>>>>>>> ddc347f4d98c1bde70cb3726989af3ead08b7d92
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
<<<<<<< HEAD
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
=======
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $roles = [
            'superadmin',
            'admin',
            'customer',
            'seller_owner',
            'seller_staff',
            'delivery_agent',
        ];

        foreach ($roles as $roleName) {
            Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
>>>>>>> ddc347f4d98c1bde70cb3726989af3ead08b7d92
    }
}
