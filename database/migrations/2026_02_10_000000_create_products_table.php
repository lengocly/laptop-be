<?php

/**
 * =============================================================================
 * NHIỆM VỤ FILE NÀY (Migration: tạo bảng products)
 * =============================================================================
 * - Migration = “phiên bản hóa” cấu trúc database: tạo/sửa/xóa bảng bằng code, có thể rollback.
 *
 * HÀM up():
 * - Chạy khi bạn gõ: php artisan migrate
 * - Schema::create('products', ...): TẠO bảng mới tên `products` trong MySQL.
 * - Các $table->...(): định nghĩa cột, kiểu dữ liệu, mặc định (vd: stock mặc định 0).
 *
 * HÀM down():
 * - Chạy khi rollback: php artisan migrate:rollback
 * - Schema::dropIfExists('products'): XÓA bảng products (hoàn tác thay đổi).
 *
 * Sau migrate, trong HeidiSQL bạn sẽ thấy bảng `products` và có thể nhìn thấy cột tương ứng.
 * =============================================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->nullable()->unique();
            $table->string('price_display');
            $table->string('image_main', 2048);
            $table->string('image_hover', 2048)->nullable();
            $table->string('cpu')->nullable();
            $table->string('ram')->nullable();
            $table->string('storage')->nullable();
            $table->string('screen')->nullable();
            $table->unsignedInteger('stock')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
