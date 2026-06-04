<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
// bảng thông tin đơn hàng
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            // user đặt hàng (dùng để biết đơn hàng này thuộc user nào)
            $table->foreignId('user_id')
                ->constrained('users') //liên kết bảng user
                ->cascadeOnDelete(); //nếu user bị xóa, đơn hàng của user đó cũng bị xóa theo

            // thông tin giao hàng
            $table->string('full_name');
            $table->string('phone', 20);
            $table->text('address');
            $table->text('note')->nullable();
            $table->text('admin_note')->nullable(); //ghi chú của admin

            // tổng tiền đơn hàng
            $table->unsignedBigInteger('subtotal')->default(0); //unsignedBigInteger vì tiền không âm và có thể là số lớn

            // trạng thái đơn hàng
            $table->enum('status', ['pending', 'processing', 'shipping', 'delivered', 'cancelled'])
            ->default('pending');
            //khi khách vừa đặt hàng, trạng thái mặc định là chờ xử lý.

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
