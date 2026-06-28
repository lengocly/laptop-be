<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('orders', 'payment_status')) {
            return;
        }
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement(
                "ALTER TABLE orders MODIFY payment_status "
                . "ENUM('unpaid', 'paid', 'failed', 'expired', 'refunded') NOT NULL DEFAULT 'unpaid'"
            );
        }
    }
    public function down(): void
    {
        if (!Schema::hasColumn('orders', 'payment_status')) {
            return;
        }
        DB::table('orders')
            ->whereIn('payment_status', ['expired', 'refunded'])
            ->update(['payment_status' => 'failed']);
        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement(
                "ALTER TABLE orders MODIFY payment_status "
                . "ENUM('unpaid', 'paid', 'failed') NOT NULL DEFAULT 'unpaid'"
            );
        }
    }
};

