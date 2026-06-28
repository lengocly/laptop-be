<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seeder gốc — chạy 1 lệnh tạo lại TOÀN BỘ dữ liệu demo.
 *
 *   php artisan migrate:fresh --seed
 *
 * Thứ tự quan trọng: user → danh mục → SP → biến thể → voucher → khách demo → đơn hàng
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            CategorySeeder::class,
            ProductSeeder::class,
            ProductVariantSeeder::class,
            VoucherSeeder::class,
            DemoUserSeeder::class,
            OrderSeeder::class,
        ]);
    }
}

