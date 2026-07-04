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
            AdminUserSeeder::class,      // admin@betatech.com
            CategorySeeder::class,       // Laptop (hãng) + Phụ kiện
            ProductSeeder::class,        // Sản phẩm mẫu
            ProductVariantSeeder::class, // Biến thể (màu tai nghe, cấu hình MacBook...)
            VoucherSeeder::class,        // Mã giảm giá demo
            DemoUserSeeder::class,       // khach@betatech.com
            OrderSeeder::class,          // Đơn hàng mẫu cho dashboard
        ]);
    }
}
