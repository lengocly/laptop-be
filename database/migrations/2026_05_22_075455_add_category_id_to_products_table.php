<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('category_id')
                ->nullable() //nullable() — sản phẩm có thể không có danh mục

                ->after('is_active') //Đặt cột ngay sau is_active (thứ tự trong bảng; MySQL)

                ->constrained('categories') //constrained('categories') — khóa ngoại trỏ lại chính bảng categories

                ->nullOnDelete(); //nullOnDelete() — xóa danh mục thì category_id của sản phẩm được set null (sản phẩm không bị xóa theo)
        });
    }
    

    //Rollback migration này sẽ gỡ FK rồi xóa cột category_id — bảng products trở lại như trước khi có danh mục.
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropColumn('category_id');
        });
    }
};
