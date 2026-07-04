<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Ghi nhận voucher đã áp dụng trên đơn hàng
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('voucher_id')
                ->nullable()
                ->after('subtotal')
                ->constrained('vouchers')
                ->nullOnDelete();

            // Số tiền được giảm (VNĐ)
            $table->unsignedBigInteger('voucher_discount')
                ->default(0)
                ->after('voucher_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['voucher_id']);
            $table->dropColumn(['voucher_id', 'voucher_discount']);
        });
    }
};
