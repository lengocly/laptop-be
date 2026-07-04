<?php

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

            // parent_id = null → danh mục cha gốc (Laptop, Phụ kiện)
            // parent_id = 1 → danh mục con (Chuột, Bàn phím…)
            // slug → FE gửi ?category=chuot

            //constrained('categories') — khóa ngoại trỏ lại chính bảng categories
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            //nullOnDelete() — xóa danh mục cha thì parent_id của con được set null (con không bị xóa theo)
            
            $table->timestamps();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('categories'); // xóa bảng categories
    }
};
