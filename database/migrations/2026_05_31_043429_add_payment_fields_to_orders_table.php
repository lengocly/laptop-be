<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('order_code')->unique()->after('id');
            $table->enum('payment_method', ['cod', 'stripe'])
                ->default('cod')
                ->after('subtotal');
            $table->enum('payment_status', ['unpaid', 'paid', 'failed'])
                ->default('unpaid')
                ->after('payment_method');
            $table->string('stripe_payment_intent_id')
                ->nullable()
                ->after('payment_status');
        });
    }
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'order_code',
                'payment_method',
                'payment_status',
                'stripe_payment_intent_id',
            ]);
        });
    }
};