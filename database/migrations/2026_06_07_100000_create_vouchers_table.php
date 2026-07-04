<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Bảng voucher/khuyến mãi — admin tạo, user lưu và dùng khi checkout
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // mã voucher, vd: BETATECH100K
            $table->string('title');          // tiêu đề hiển thị trên thẻ
            $table->text('description')->nullable();

            // fixed = giảm cố định (VNĐ), percent = giảm theo %
            $table->enum('discount_type', ['fixed', 'percent']);
            $table->unsignedBigInteger('discount_value'); // VNĐ hoặc % (1-100)
            $table->unsignedBigInteger('min_order_amount')->default(0); // đơn tối thiểu
            $table->unsignedBigInteger('max_discount')->nullable();     // trần giảm (cho %)

            $table->timestamp('starts_at')->nullable();  // bắt đầu hiệu lực
            $table->timestamp('expires_at');             // HSD — hạn sử dụng

            $table->unsignedInteger('usage_limit')->nullable(); // null = không giới hạn
            $table->unsignedInteger('used_count')->default(0);

            $table->boolean('is_active')->default(true);

            // admin tạo voucher
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
