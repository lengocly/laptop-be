<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stripe_webhook_events', function (Blueprint $table) {
            $table->string('status', 16)->default('processed')->after('type');
            $table->text('error_message')->nullable()->after('status');
        });

        DB::table('stripe_webhook_events')->update(['status' => 'processed']);

        // Voucher đã used_at → reservation_status = used
        if (Schema::hasColumn('user_vouchers', 'used_at')) {
            DB::table('user_vouchers')
                ->whereNotNull('used_at')
                ->update(['reservation_status' => 'used']);
        }

        // Đơn Stripe cũ chưa thanh toán → gán expires_at nếu thiếu
        if (Schema::hasColumn('orders', 'expires_at')) {
            $expireMinutes = (int) config('commerce.stripe_order_expire_minutes', 30);

            $orders = DB::table('orders')
                ->where('payment_method', 'stripe')
                ->where('payment_status', 'unpaid')
                ->whereNull('expires_at')
                ->whereIn('order_status', ['pending_payment', 'confirmed'])
                ->get(['id', 'created_at']);

            foreach ($orders as $order) {
                DB::table('orders')->where('id', $order->id)->update([
                    'expires_at' => \Illuminate\Support\Carbon::parse($order->created_at)
                        ->addMinutes($expireMinutes),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('stripe_webhook_events', function (Blueprint $table) {
            $table->dropColumn(['status', 'error_message']);
        });
    }
};
