<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('group_key');
            $table->string('group_label');
            $table->string('option_label');
            $table->string('sku')->nullable();
            $table->string('price_display')->nullable();
            $table->string('price_original')->nullable();
            $table->unsignedInteger('stock')->default(0);
            $table->string('image_main', 2048)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};

