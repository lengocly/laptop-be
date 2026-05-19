<?php

/**
 * =============================================================================
 * NHIỆM VỤ FILE NÀY
 * =============================================================================
 * - Tạo bảng `categories`: danh mục cho menu (Laptop / Phụ kiện và nhóm con).
 * - Thêm cột `category_id` vào `products` để mỗi sản phẩm thuộc một danh mục.
 * - parent_id = NULL → mục cấp 1 (hiển thị trên thanh menu chính).
 * - parent_id trỏ tới id cha → mục cấp 2 (dropdown), giống cấu trúc menu shop lớn.
 *
 * down(): gỡ foreign key + xóa cột + xóa bảng categories.
 * =============================================================================
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'category_id')) {
                $table->foreignId('category_id')->nullable()->after('id')->constrained()->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'category_id')) {
                $table->dropForeign(['category_id']);
                $table->dropColumn('category_id');
            }
        });

        Schema::dropIfExists('categories');
    }
};
