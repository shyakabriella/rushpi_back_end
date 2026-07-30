<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear the Spatie permission cache.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $roles = [
            [
                'name' => 'admin',
                'guard_name' => 'web',
            ],
            [
                'name' => 'customer',
                'guard_name' => 'web',
            ],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                [
                    'name' => $role['name'],
                    'guard_name' => $role['guard_name'],
                ],
                [
                    'name' => $role['name'],
                    'guard_name' => $role['guard_name'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        // Clear the cache again after inserting roles.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}