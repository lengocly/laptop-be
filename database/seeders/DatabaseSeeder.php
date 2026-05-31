<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;
    public function run(): void
    {
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call(CategorySeeder::class);  // trước

        //ProductSeeder là file riêng — Laravel không tự chạy nó nếu bạn không gọi.
        $this->call(ProductSeeder::class);

        //Biến thể sản phẩm: màu sắc, bộ nhớ, cấu hình, ...
        $this->call(ProductVariantSeeder::class);
    }
}
