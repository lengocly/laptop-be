<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Chỉ tạo tài khoản admin — KHÔNG có SP, danh mục, đơn, voucher mẫu.
 * Dùng khi muốn nhập toàn bộ dữ liệu bằng Admin Panel.
 *
 *   php artisan migrate:fresh --seed --seeder=FreshManualSeeder
 */
class FreshManualSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
        ]);
    }
}
