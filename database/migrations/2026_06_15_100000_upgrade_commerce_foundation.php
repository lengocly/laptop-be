<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('order_status', 32)->default('pending_payment')->after('user_id');
            $table->string('fulfillment_status', 32)->default('unfulfilled')->after('order_status');
            $table->unsignedBigInteger('items_subtotal')->default(0)->after('admin_note');
            $table->unsignedInteger('to_district_id')->nullable()->after('address');
            $table->string('to_ward_code', 20)->nullable()->after('to_district_id');
            $table->timestamp('expires_at')->nullable()->after('stripe_payment_intent_id');
            $table->timestamp('inventory_released_at')->nullable()->after('expires_at');
            $table->timestamp('voucher_released_at')->nullable()->after('inventory_released_at');
        });
        if (Schema::hasColumn('orders', 'status')) {
            foreach (DB::table('orders')->orderBy('id')->get() as $order) {
                $orderStatus = 'confirmed';
                $fulfillment = 'unfulfilled';
                if ($order->status === 'cancelled') {
                    $orderStatus = 'cancelled';
                } elseif ($order->status === 'delivered') {
                    $fulfillment = 'delivered';
                } elseif ($order->status === 'shipping') {
                    $fulfillment = 'shipping';
                } elseif ($order->status === 'processing') {
                    $fulfillment = 'processing';
                } elseif (
                    $order->status === 'pending'
                    && $order->payment_method === 'stripe'
                    && $order->payment_status !== 'paid'
                ) {
                    $orderStatus = 'pending_payment';
                }
                DB::table('orders')->where('id', $order->id)->update([
                    'order_status' => $orderStatus,
                    'fulfillment_status' => $fulfillment,
                    'items_subtotal' => max(0, (int) $order->subtotal - (int) ($order->shipping_fee ?? 0) + (int) ($order->voucher_discount ?? 0)),
                ]);
            }
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }
        Schema::table('user_vouchers', function (Blueprint $table) {
            $table->string('reservation_status', 16)->default('available')->after('voucher_id');
            $table->foreignId('reserved_order_id')->nullable()->after('reservation_status')
                ->constrained('orders')->nullOnDelete();
            $table->timestamp('reserved_at')->nullable()->after('reserved_order_id');
        });
        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 32);
            $table->integer('quantity');
            $table->string('idempotency_key')->unique();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 32);
            $table->unsignedBigInteger('amount');
            $table->string('currency', 8)->default('vnd');
            $table->string('status', 32);
            $table->string('provider_reference')->nullable();
            $table->string('idempotency_key')->unique();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
        Schema::create('stripe_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id')->unique();
            $table->string('type', 64);
            $table->timestamp('processed_at')->useCurrent();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('stripe_webhook_events');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('inventory_transactions');
        Schema::table('user_vouchers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reserved_order_id');
            $table->dropColumn(['reservation_status', 'reserved_at']);
        });
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('status', ['pending', 'processing', 'shipping', 'delivered', 'cancelled'])
                ->default('pending');
            $table->dropColumn([
                'order_status',
                'fulfillment_status',
                'items_subtotal',
                'to_district_id',
                'to_ward_code',
                'expires_at',
                'inventory_released_at',
                'voucher_released_at',
            ]);
        });
    }
};

