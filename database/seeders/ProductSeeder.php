<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;
use App\Models\Category;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $laptop   = Category::where('slug', 'laptop')->first();
        $chuot    = Category::where('slug', 'chuot')->first();
        $banPhim  = Category::where('slug', 'ban-phim')->first();
        $taiNghe  = Category::where('slug', 'tai-nghe')->first();

        Product::create([
            'name'           => 'ASUS Vivobook 15',
            'slug'           => 'asus-vivobook-15',
            'price_display'  => '15.990.000 ₫',
            'price_original' => '18.990.000 ₫',
            'image_main'     => 'products/asus1.jpg',
            'image_hover'    => 'products/asus2.webp',
            'cpu'            => 'Intel Core i5-1335U',
            'ram'            => '16GB',
            'storage'        => '512GB SSD',
            'screen'         => '15.6" FHD',
            'stock'          => 10,
            'is_active'      => true,
            'category_id'    => $laptop->id,
        ]);

        Product::create([
            'name'           => 'Dell Inspiron 14',
            'slug'           => 'dell-inspiron-14',
            'price_display'  => '18.490.000 ₫',
            'price_original' => '21.990.000 ₫',
            'image_main'     => 'products/dell1.jpg',
            'image_hover'    => 'products/dell2.jpg',
            'cpu'            => 'AMD Ryzen 5 7530U',
            'ram'            => '8GB',
            'storage'        => '256GB SSD',
            'screen'         => '14" FHD',
            'stock'          => 5,
            'is_active'      => true,
            'category_id'    => $laptop->id,
        ]);

        Product::create([
            'name'           => 'MacBook Pro 14',
            'slug'           => 'macbook-pro-14',
            'price_display'  => '42.990.000 ₫',
            'price_original' => '46.990.000 ₫',
            'image_main'     => 'products/mac1.png',
            'image_hover'    => 'products/mac2.png',
            'cpu'            => 'Apple M3',
            'ram'            => '16GB',
            'storage'        => '512GB SSD',
            'screen'         => '14.2" Retina',
            'stock'          => 8,
            'is_active'      => true,
            'category_id'    => $laptop->id,
        ]);

        Product::create([
            'name'           => 'Lenovo IdeaPad Slim 5',
            'slug'           => 'lenovo-ideapad-slim-5',
            'price_display'  => '16.990.000 ₫',
            'price_original' => '19.990.000 ₫',
            'image_main'     => 'products/l1.webp',
            'image_hover'    => 'products/l2.webp',
            'cpu'            => 'AMD Ryzen 5 5500U',
            'ram'            => '16GB',
            'storage'        => '512GB SSD',
            'screen'         => '15.6" FHD',
            'stock'          => 12,
            'is_active'      => true,
            'category_id'    => $laptop->id,
        ]);

        Product::create([
            'name'           => 'HP Pavilion 15',
            'slug'           => 'hp-pavilion-15',
            'price_display'  => '17.490.000 ₫',
            'price_original' => '20.990.000 ₫',
            'image_main'     => 'products/h1.webp',
            'image_hover'    => 'products/h2.webp',
            'cpu'            => 'Intel Core i5-1235U',
            'ram'            => '8GB',
            'storage'        => '512GB SSD',
            'screen'         => '15.6" FHD',
            'stock'          => 7,
            'is_active'      => true,
            'category_id'    => $laptop->id,
        ]);

        // ========== CHUỘT ==========
        Product::create([
            'name'           => 'Chuột Logitech G102',
            'slug'           => 'chuot-logitech-g102',
            'price_display'  => '390.000 ₫',
            'price_original' => '490.000 ₫',
            'image_main'     => 'products/c1.webp',
            'image_hover'    => 'products/c2.webp',
            'cpu'            => null,
            'ram'            => null,
            'storage'        => null,
            'screen'         => null,
            'stock'          => 30,
            'is_active'      => true,
            'category_id'    => $chuot->id,
        ]);

        Product::create([
            'name'           => 'Chuột Razer DeathAdder',
            'slug'           => 'chuot-razer-deathadder',
            'price_display'  => '890.000 ₫',
            'price_original' => '1.190.000 ₫',
            'image_main'     => 'products/ch1.webp',
            'image_hover'    => 'products/ch2.png',
            'cpu'            => null,
            'ram'            => null,
            'storage'        => null,
            'screen'         => null,
            'stock'          => 25,
            'is_active'      => true,
            'category_id'    => $chuot->id,
        ]);

        // ========== BÀN PHÍM ==========
        Product::create([
            'name'           => 'Bàn phím Keychron K2',
            'slug'           => 'ban-phim-keychron-k2',
            'price_display'  => '1.890.000 ₫',
            'price_original' => '2.290.000 ₫',
            'image_main'     => 'products/b1.webp',
            'image_hover'    => 'products/b2.webp',
            'cpu'            => null,
            'ram'            => null,
            'storage'        => null,
            'screen'         => null,
            'stock'          => 15,
            'is_active'      => true,
            'category_id'    => $banPhim->id,
        ]);

        Product::create([
            'name'           => 'Bàn phím Logitech K380',
            'slug'           => 'ban-phim-logitech-k380',
            'price_display'  => '790.000 ₫',
            'price_original' => '990.000 ₫',
            'image_main'     => 'products/ba1.webp',
            'image_hover'    => 'products/ba2.webp',
            'cpu'            => null,
            'ram'            => null,
            'storage'        => null,
            'screen'         => null,
            'stock'          => 20,
            'is_active'      => true,
            'category_id'    => $banPhim->id,
        ]);

        // ========== TAI NGHE ==========
        Product::create([
            'name'           => 'Tai nghe Sony WH-CH520',
            'slug'           => 'tai-nghe-sony-ch520',
            'price_display'  => '1.290.000 ₫',
            'price_original' => '1.590.000 ₫',
            'image_main'     => 'products/t1.webp',
            'image_hover'    => 'products/t2.webp',
            'cpu'            => null,
            'ram'            => null,
            'storage'        => null,
            'screen'         => null,
            'stock'          => 18,
            'is_active'      => true,
            'category_id'    => $taiNghe->id,
        ]);

        Product::create([
            'name'           => 'Tai nghe JBL Tune 520',
            'slug'           => 'tai-nghe-jbl-tune-520',
            'price_display'  => '990.000 ₫',
            'price_original' => '1.290.000 ₫',
            'image_main'     => 'products/ta1.webp',
            'image_hover'    => 'products/ta2.webp',
            'cpu'            => null,
            'ram'            => null,
            'storage'        => null,
            'screen'         => null,
            'stock'          => 22,
            'is_active'      => true,
            'category_id'    => $taiNghe->id,
        ]);
    }
}