<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use Illuminate\Support\Facades\Hash;
use App\Models\User;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {

        User::updateOrCreate(
            ['email' => 'admin@betatech.com'],
            [
                'name' => 'BetaTech Admin',
                'password' => Hash::make(env('ADMIN_PASSWORD', 'Admin123456')),
                'email_verified_at' => now(),
                'is_admin' => true,
            ]
        );
    }
}

