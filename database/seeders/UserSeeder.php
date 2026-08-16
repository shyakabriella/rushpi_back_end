<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        /*
        |--------------------------------------------------------------------------
        | RushPi Administrator
        |--------------------------------------------------------------------------
        */

        User::updateOrCreate(
            [
                'email' => 'admin@rushpi.com',
            ],
            [
                'name' => 'RushPi Electronics Admin',
                'email' => 'admin@rushpi.com',
                'phone' => '+250788000000',
                'password' => Hash::make('Admin@12345'),
                'role' => User::ROLE_ADMIN,
                'status' => User::STATUS_ACTIVE,
                'address' => 'Kigali, Rwanda',
                'email_verified_at' => now(),
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | NTEZINET Paint Dealer
        |--------------------------------------------------------------------------
        */

        $ntezinetDealer = User::updateOrCreate(
            [
                'email' => 'nteznet@gmail.com',
            ],
            [
                'name' => 'NTEZINET Paint',
                'email' => 'nteznet@gmail.com',
                'phone' => '0784987353',
                'password' => Hash::make('ntezinet@2026'),
                'role' => User::ROLE_DEALER,
                'status' => User::STATUS_ACTIVE,
                'address' => 'Kigali, Rwanda',
                'email_verified_at' => now(),
            ]
        );

        /*
         * If Spatie Permission is being used
         * for role middleware, make sure this
         * user also receives the dealer role.
         */
        if (
            method_exists(
                $ntezinetDealer,
                'syncRoles'
            )
        ) {
            $ntezinetDealer->syncRoles([
                'dealer',
            ]);
        }
    }
}