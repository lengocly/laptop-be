<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

// Khách hàng demo — đăng nhập để đặt hàng / xem lịch sử
class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'khach@betatech.com'],
            [
                'name' => 'Nguyễn Văn Demo',
                'password' => Hash::make('Khach123456'),
                'email_verified_at' => now(),
                'is_admin' => false,
            ]
        );
    }
}
