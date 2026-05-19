<?php

/**
 * =============================================================================
 * NHIỆM VỤ FILE NÀY (DatabaseSeeder)
 * =============================================================================
 * - Điểm vào khi chạy: php artisan db:seed
 * - File này gọi các Seeder con (vd: ProductSeeder) để nạp dữ liệu mẫu sau migrate.
 *
 * HÀM run():
 * - Thực thi lần lượt các seeder bạn liệt kê bên trong.
 * - User::factory() (mặc định Laravel): tạo user test trong bảng users (phục vụ đăng nhập sau này).
 * - ProductSeeder: gieo dữ liệu laptop vào bảng products.
 *
 * Lưu ý: chạy nhiều lần có thể trùng email user hoặc trùng slug sản phẩm — tùy ràng buộc DB.
 * =============================================================================
 */

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call(ProductSeeder::class);
    }
}
