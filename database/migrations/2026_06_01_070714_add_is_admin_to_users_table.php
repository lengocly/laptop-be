<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// // Kiểu boolean:
// 0 / false = khách hàng thường
// 1 / true  = quản trị viên

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            //default(false) → đăng ký mới luôn là khách, không ai tự thành admin
            $table->boolean('is_admin')->default(false)->after('password');
        });
    }


    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_admin');
        });
    }
};
