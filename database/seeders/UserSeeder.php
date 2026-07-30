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
        User::updateOrCreate(
            [
                'email' => 'admin@rushpi.com',
            ],
            [
                'name' => 'rushpi Electronics Admin',
                'email' => 'admin@rushpi.com',
                'phone' => '+250788000000',
                'password' => Hash::make('Admin@12345'),
                'role' => User::ROLE_ADMIN,
                'status' => User::STATUS_ACTIVE,
                'address' => 'Kigali, Rwanda',
                'email_verified_at' => now(),
            ]
        );
    }
}