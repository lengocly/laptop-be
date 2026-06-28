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
            $table->string('processing_owner', 36)->nullable()->after('error_message');
            $table->timestamp('processing_started_at')->nullable()->after('processing_owner');
        });
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE stripe_webhook_events MODIFY processed_at TIMESTAMP NULL DEFAULT NULL'
            );
        }
        DB::table('stripe_webhook_events')
            ->whereIn('status', ['processing', 'failed'])
            ->update(['processed_at' => null]);
        if (Schema::hasColumn('orders', 'payment_status')
            && Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE orders MODIFY payment_status '
                . "ENUM('unpaid', 'paid', 'failed', 'expired', 'refunded', 'requires_refund') "
                . "NOT NULL DEFAULT 'unpaid'"
            );
        }
    }
    public function down(): void
    {
        if (Schema::hasColumn('orders', 'payment_status')
            && Schema::getConnection()->getDriverName() === 'mysql') {
            DB::table('orders')
                ->where('payment_status', 'requires_refund')
                ->update(['payment_status' => 'failed']);
            DB::statement(
                'ALTER TABLE orders MODIFY payment_status '
                . "ENUM('unpaid', 'paid', 'failed', 'expired', 'refunded') "
                . "NOT NULL DEFAULT 'unpaid'"
            );
        }
        Schema::table('stripe_webhook_events', function (Blueprint $table) {
            $table->dropColumn(['processing_owner', 'processing_started_at']);
        });
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::table('stripe_webhook_events')
                ->whereNull('processed_at')
                ->update(['processed_at' => now()]);
            DB::statement(
                'ALTER TABLE stripe_webhook_events MODIFY processed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP'
            );
        }
    }
};

