<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * biến thể sản phẩm: màu sắc, bộ nhớ, cấu hình, ...
     */
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            // Nhóm thuộc tính (cùng 1 SP dùng chung 1 nhóm)
            $table->string('group_key');   // 'color' | 'config'
            $table->string('group_label'); // 'Màu sắc' | 'Cấu hình'
            $table->string('option_label'); // 'Trắng' | '16GB / 512GB'
            $table->string('sku')->nullable();
            $table->string('price_display')->nullable();  // null = dùng giá SP cha
            $table->string('price_original')->nullable();
            $table->unsignedInteger('stock')->default(0);
            $table->string('image_main', 2048)->nullable(); // ảnh riêng variant
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
