<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // Danh mục con = hãng / loại phụ kiện (không còn slug "laptop" chung)
        $asus    = Category::where('slug', 'asus')->first();
        $dell    = Category::where('slug', 'dell')->first();
        $macbook = Category::where('slug', 'macbook')->first();
        $lenovo  = Category::where('slug', 'lenovo')->first();
        $hp      = Category::where('slug', 'hp')->first();
        $chuot   = Category::where('slug', 'chuot')->first();
        $banPhim = Category::where('slug', 'ban-phim')->first();
        $taiNghe = Category::where('slug', 'tai-nghe')->first();

        // ========== LAPTOP THEO HÃNG ==========
        Product::create([
            'name' => 'ASUS Vivobook 15',
            'slug' => 'asus-vivobook-15',
            'price_display' => '15.990.000 ₫',
            'price_original' => '18.990.000 ₫',
            'image_main' => 'products/asus1.jpg',
            'image_hover' => 'products/asus2.webp',
            'cpu' => 'Intel Core i5-1335U',
            'ram' => '16GB',
            'storage' => '512GB SSD',
            'screen' => '15.6" FHD',
            'stock' => 10,
            'is_active' => true,
            'category_id' => $asus->id,
        ]);

        Product::create([
            'name' => 'Dell Inspiron 14',
            'slug' => 'dell-inspiron-14',
            'price_display' => '18.490.000 ₫',
            'price_original' => '21.990.000 ₫',
            'image_main' => 'products/dell1.jpg',
            'image_hover' => 'products/dell2.jpg',
            'cpu' => 'AMD Ryzen 5 7530U',
            'ram' => '8GB',
            'storage' => '256GB SSD',
            'screen' => '14" FHD',
            'stock' => 5,
            'is_active' => true,
            'category_id' => $dell->id,
        ]);

        Product::create([
            'name' => 'MacBook Pro 14',
            'slug' => 'macbook-pro-14',
            'price_display' => '42.990.000 ₫',
            'price_original' => '46.990.000 ₫',
            'image_main' => 'products/mac1.png',
            'image_hover' => 'products/mac2.png',
            'cpu' => 'Apple M3',
            'ram' => '16GB',
            'storage' => '512GB SSD',
            'screen' => '14.2" Retina',
            'stock' => 8,
            'is_active' => true,
            'category_id' => $macbook->id,
        ]);

        Product::create([
            'name' => 'Lenovo IdeaPad Slim 5',
            'slug' => 'lenovo-ideapad-slim-5',
            'price_display' => '16.990.000 ₫',
            'price_original' => '19.990.000 ₫',
            'image_main' => 'products/l1.webp',
            'image_hover' => 'products/l2.webp',
            'cpu' => 'AMD Ryzen 5 5500U',
            'ram' => '16GB',
            'storage' => '512GB SSD',
            'screen' => '15.6" FHD',
            'stock' => 12,
            'is_active' => true,
            'category_id' => $lenovo->id,
        ]);

        Product::create([
            'name' => 'HP Pavilion 15',
            'slug' => 'hp-pavilion-15',
            'price_display' => '17.490.000 ₫',
            'price_original' => '20.990.000 ₫',
            'image_main' => 'products/h1.webp',
            'image_hover' => 'products/h2.webp',
            'cpu' => 'Intel Core i5-1235U',
            'ram' => '8GB',
            'storage' => '512GB SSD',
            'screen' => '15.6" FHD',
            'stock' => 7,
            'is_active' => true,
            'category_id' => $hp->id,
        ]);

        // Thêm vài laptop mẫu cùng hãng (ảnh tạm có sẵn)
        Product::create([
            'name' => 'ASUS TUF Gaming A15',
            'slug' => 'asus-tuf-gaming-a15',
            'price_display' => '22.990.000 ₫',
            'price_original' => '25.990.000 ₫',
            'image_main' => 'products/asus2.webp',
            'image_hover' => 'products/asus1.jpg',
            'cpu' => 'AMD Ryzen 7 7735HS',
            'ram' => '16GB',
            'storage' => '512GB SSD',
            'screen' => '15.6" 144Hz',
            'stock' => 6,
            'is_active' => true,
            'category_id' => $asus->id,
        ]);

        Product::create([
            'name' => 'Dell XPS 13',
            'slug' => 'dell-xps-13',
            'price_display' => '28.990.000 ₫',
            'price_original' => '31.990.000 ₫',
            'image_main' => 'products/dell2.jpg',
            'image_hover' => 'products/dell1.jpg',
            'cpu' => 'Intel Core i7-1355U',
            'ram' => '16GB',
            'storage' => '512GB SSD',
            'screen' => '13.4" FHD+',
            'stock' => 4,
            'is_active' => true,
            'category_id' => $dell->id,
        ]);

        // ========== CHUỘT ==========
        Product::create([
            'name' => 'Chuột Logitech G102',
            'slug' => 'chuot-logitech-g102',
            'price_display' => '390.000 ₫',
            'price_original' => '490.000 ₫',
            'image_main' => 'products/c1.webp',
            'image_hover' => 'products/c2.webp',
            'stock' => 30,
            'is_active' => true,
            'category_id' => $chuot->id,
        ]);

        Product::create([
            'name' => 'Chuột Razer DeathAdder',
            'slug' => 'chuot-razer-deathadder',
            'price_display' => '890.000 ₫',
            'price_original' => '1.190.000 ₫',
            'image_main' => 'products/ch1.webp',
            'image_hover' => 'products/ch2.png',
            'stock' => 25,
            'is_active' => true,
            'category_id' => $chuot->id,
        ]);

        // ========== BÀN PHÍM ==========
        Product::create([
            'name' => 'Bàn phím Keychron K2',
            'slug' => 'ban-phim-keychron-k2',
            'price_display' => '1.890.000 ₫',
            'price_original' => '2.290.000 ₫',
            'image_main' => 'products/b1.webp',
            'image_hover' => 'products/b2.webp',
            'stock' => 15,
            'is_active' => true,
            'category_id' => $banPhim->id,
        ]);

        Product::create([
            'name' => 'Bàn phím Logitech K380',
            'slug' => 'ban-phim-logitech-k380',
            'price_display' => '790.000 ₫',
            'price_original' => '990.000 ₫',
            'image_main' => 'products/ba1.webp',
            'image_hover' => 'products/ba2.webp',
            'stock' => 20,
            'is_active' => true,
            'category_id' => $banPhim->id,
        ]);

        // ========== TAI NGHE ==========
        Product::create([
            'name' => 'Tai nghe Sony WH-CH520',
            'slug' => 'tai-nghe-sony-ch520',
            'price_display' => '1.290.000 ₫',
            'price_original' => '1.590.000 ₫',
            'image_main' => 'products/t1.webp',
            'image_hover' => 'products/t2.webp',
            'stock' => 18,
            'is_active' => true,
            'category_id' => $taiNghe->id,
        ]);

        Product::create([
            'name' => 'Tai nghe JBL Tune 520',
            'slug' => 'tai-nghe-jbl-tune-520',
            'price_display' => '990.000 ₫',
            'price_original' => '1.290.000 ₫',
            'image_main' => 'products/ta1.webp',
            'image_hover' => 'products/ta2.webp',
            'stock' => 22,
            'is_active' => true,
            'category_id' => $taiNghe->id,
        ]);
    }
}
