<?php

/**
 * =============================================================================
 * NHIỆM VỤ FILE NÀY (Seeder)
 * =============================================================================
 * - Seeder = "gieo" dữ liệu mẫu vào database sau khi đã có bảng (migration).
 * - File này tạo vài dòng LAPTOP trong bảng `products` để bạn có dữ liệu test
 *   mà không cần nhập tay trong HeidiSQL. Hoặc import file
 *   database/sql/betatech_ecommerce.sql (đủ 40 SP + danh mục).
 *
 * CÁCH CHẠY (trong thư mục backend):
 *   php artisan db:seed --class=ProductSeeder
 * hoặc gọi từ DatabaseSeeder: php artisan db:seed
 *
 * HÀM run(): thực hiện chức năng gì?
 * - Dùng Product::create([...]) để INSERT từng sản phẩm vào MySQL.
 * - Mỗi lần chạy seeder sẽ THÊM bản ghi mới (trừ khi bạn xóa bảng / truncate).
 * =============================================================================
 */

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'name' => 'MacBook Air M3 13" 256GB',
                'slug' => 'macbook-air-m3-13-256',
                'price_display' => '26.990.000đ',
                'image_main' => 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?auto=format&fit=crop&w=720&q=80',
                'image_hover' => 'https://images.unsplash.com/photo-1611186871348-b1ce696e52c9?auto=format&fit=crop&w=720&q=80',
                'cpu' => 'Apple M3',
                'ram' => '8GB',
                'storage' => '256GB SSD',
                'screen' => '13.6" Liquid Retina',
                'stock' => 10,
                'is_active' => true,
            ],
            [
                'name' => 'Dell XPS 15 — Core i7, 16GB, 512GB SSD',
                'slug' => 'dell-xps-15-i7',
                'price_display' => '38.490.000đ',
                'image_main' => 'https://images.unsplash.com/photo-1593642632823-8f785ba67e45?auto=format&fit=crop&w=720&q=80',
                'image_hover' => 'https://images.unsplash.com/photo-1588872657578-7efd1f1555ed?auto=format&fit=crop&w=720&q=80',
                'cpu' => 'Intel Core i7',
                'ram' => '16GB',
                'storage' => '512GB SSD',
                'screen' => '15.6" FHD+',
                'stock' => 5,
                'is_active' => true,
            ],
            [
                'name' => 'Lenovo ThinkPad E14 Gen 5',
                'slug' => 'lenovo-thinkpad-e14-g5',
                'price_display' => '17.990.000đ',
                'image_main' => 'https://images.unsplash.com/photo-1525547719571-a2d4ac8945e2?auto=format&fit=crop&w=720&q=80',
                'image_hover' => 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?auto=format&fit=crop&w=720&q=80',
                'cpu' => 'AMD Ryzen 5',
                'ram' => '16GB',
                'storage' => '512GB SSD',
                'screen' => '14" FHD',
                'stock' => 8,
                'is_active' => true,
            ],
        ];

        foreach ($rows as $row) {
            Product::create($row);
        }
    }
}
