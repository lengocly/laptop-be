<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use App\Models\Category;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //Nhóm cha Laptop
        $laptop = Category::create([
            'name' => 'Laptop',
            'slug' => 'laptop-group',
            'parent_id' => null,
        ]);

        //Danh mục con để filter sản phẩm laptop
        Category::create([
            'name' => 'Laptop',
            'slug' => 'laptop',
            'parent_id' => $laptop->id,
        ]);

        //Nhóm cha Phụ kiện
        $phuKien = Category::create([
            'name' => 'Phụ kiện',
            'slug' => 'phu-kien',
            'parent_id' => null,
        ]);

        Category::create(['name' => 'Chuột',      'slug' => 'chuot',      'parent_id' => $phuKien->id]);
        Category::create(['name' => 'Bàn phím',   'slug' => 'ban-phim',   'parent_id' => $phuKien->id]);
        Category::create(['name' => 'Tai nghe',   'slug' => 'tai-nghe',   'parent_id' => $phuKien->id]);
   

       
    }
}
