<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')
                ->constrained('products')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->string('title', 100);
            $table->text('content');
            $table->boolean('is_verified_purchase')->default(true);
            $table->timestamps();
            $table->unique(['product_id', 'user_id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('product_reviews');
    }
};

