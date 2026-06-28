<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {

        $laptopGroup = Category::create([
            'name' => 'Laptop',
            'slug' => 'laptop-group',
            'parent_id' => null,
            'image' => 'products/mac1.png',
            'sort_order' => 1,
        ]);


        $laptopBrands = [
            ['name' => 'ASUS', 'slug' => 'asus', 'image' => 'products/asus1.jpg'],
            ['name' => 'Dell', 'slug' => 'dell', 'image' => 'products/dell1.jpg'],
            ['name' => 'MacBook', 'slug' => 'macbook', 'image' => 'products/mac1.png'],
            ['name' => 'Lenovo', 'slug' => 'lenovo', 'image' => 'products/l1.webp'],
            ['name' => 'HP', 'slug' => 'hp', 'image' => 'products/h1.webp'],
        ];

        foreach ($laptopBrands as $i => $brand) {
            Category::create([
                'name' => $brand['name'],
                'slug' => $brand['slug'],
                'parent_id' => $laptopGroup->id,
                'image' => $brand['image'],
                'sort_order' => $i + 1,
            ]);
        }


        $phuKien = Category::create([
            'name' => 'Phụ kiện',
            'slug' => 'phu-kien',
            'parent_id' => null,
            'image' => 'products/c1.webp',
            'sort_order' => 2,
        ]);

        $accessories = [
            ['name' => 'Chuột', 'slug' => 'chuot', 'image' => 'products/c1.webp'],
            ['name' => 'Bàn phím', 'slug' => 'ban-phim', 'image' => 'products/ba1.webp'],
            ['name' => 'Tai nghe', 'slug' => 'tai-nghe', 'image' => 'products/t1.webp'],
        ];

        foreach ($accessories as $i => $item) {
            Category::create([
                'name' => $item['name'],
                'slug' => $item['slug'],
                'parent_id' => $phuKien->id,
                'image' => $item['image'],
                'sort_order' => $i + 1,
            ]);
        }
    }
}

