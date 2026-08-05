<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RoleSeeder extends Seeder
{
    /**
     * Seed the RushPi system roles.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();

        DB::transaction(
            function (): void {
                foreach (User::ROLES as $roleName) {
                    Role::findOrCreate(
                        $roleName,
                        'web'
                    );
                }
            }
        );

        app(PermissionRegistrar::class)
            ->forgetCachedPermissions();
    }
}
