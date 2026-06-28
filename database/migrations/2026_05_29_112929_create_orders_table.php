<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('full_name');
            $table->string('phone', 20);
            $table->text('address');
            $table->text('note')->nullable();
            $table->text('admin_note')->nullable();
            $table->unsignedBigInteger('subtotal')->default(0);
            $table->enum('status', ['pending', 'processing', 'shipping', 'delivered', 'cancelled'])
            ->default('pending');
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};

