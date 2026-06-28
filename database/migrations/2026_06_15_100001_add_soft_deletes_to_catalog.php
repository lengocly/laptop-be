<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->softDeletes();
        });
        Schema::table('product_variants', function (Blueprint $table) {
            $table->softDeletes();
        });
        Schema::table('categories', function (Blueprint $table) {
            $table->softDeletes();
        });
    }
    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->dropSoftDeletes());
        Schema::table('product_variants', fn (Blueprint $table) => $table->dropSoftDeletes());
        Schema::table('categories', fn (Blueprint $table) => $table->dropSoftDeletes());
    }
};

