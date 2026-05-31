<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// dùng để lưu các sản phẩm nằm trong đơn hàng đó
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            
            // đơn hàng cha
            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            // sản phẩm được mua
            $table->unsignedBigInteger('product_id');

            // biến thể sản phẩm nếu có, ví dụ: màu, phiên bản, loại máy
            $table->unsignedBigInteger('product_variant_id')->nullable();

            // lưu lại tên sản phẩm tại thời điểm đặt hàng
            $table->string('product_name');

            // tên option/biến thể, ví dụ: "Màu đen", "FX-580VN X"
            $table->string('option_label')->nullable();

            // giá 1 sản phẩm tại thời điểm mua
            $table->unsignedBigInteger('price');

            // số lượng mua
            $table->unsignedInteger('quantity');

            // thành tiền = price * quantity
            $table->unsignedBigInteger('line_total');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
