<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            // Ảnh icon danh mục (path storage, vd: products/asus1.jpg)
            $table->string('image')->nullable()->after('slug');
            // Thứ tự hiển thị (nhỏ hơn = lên trước)
            $table->unsignedSmallInteger('sort_order')->default(0)->after('parent_id');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['image', 'sort_order']);
        });
    }
};
