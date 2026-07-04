<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        //thêm cột price_original sau price_display
        Schema::table('products', function (Blueprint $table) {
            $table->string('price_original')->nullable()->after('price_display');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //xoá cột price_original
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('price_original');
        });
    }
};
