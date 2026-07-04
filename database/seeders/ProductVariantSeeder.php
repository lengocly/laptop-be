<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use App\Models\Product;
use App\Models\ProductVariant;

class ProductVariantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
         // --- Màu: Tai nghe Sony ---
         $sony = Product::where('slug', 'tai-nghe-sony-ch520')->first();
         if ($sony) {
             ProductVariant::create([
                 'product_id' => $sony->id,
                 'group_key' => 'color',
                 'group_label' => 'Màu sắc',
                 'option_label' => 'Trắng',
                 'stock' => 10,
                 'image_main' => 'products/t1.webp',
                 'sort_order' => 1,
             ]);
             ProductVariant::create([
                 'product_id' => $sony->id,
                 'group_key' => 'color',
                 'group_label' => 'Màu sắc',
                 'option_label' => 'Xanh',
                 'stock' => 8,
                 'image_main' => 'products/t2.webp',
                 'sort_order' => 2,
             ]);
         }
         // --- Cấu hình: MacBook ---
         $mac = Product::where('slug', 'macbook-pro-14')->first();
        if ($mac) {
            ProductVariant::create([
                 'product_id' => $mac->id,
                 'group_key' => 'config',
                 'group_label' => 'Cấu hình',
                 'option_label' => '16GB / 512GB',
                 'price_display' => '42.990.000 ₫',
                 'price_original' => '46.990.000 ₫',
                 'stock' => 5,
                 'image_main' => 'products/mac1.png',
                 'sort_order' => 1,
            ]);
            ProductVariant::create([
                 'product_id' => $mac->id,
                 'group_key' => 'config',
                 'group_label' => 'Cấu hình',
                 'option_label' => '32GB / 1TB',
                 'price_display' => '52.990.000 ₫',
                 'price_original' => '56.990.000 ₫',
                 'stock' => 2,
                 'image_main' => 'products/mac2.png',
                 'sort_order' => 2,
            ]);
        }
         // Chuột / ASUS / ... : không create → không có variant
    }
}
