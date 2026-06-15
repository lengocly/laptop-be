<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Enums\VoucherReservationStatus;
use App\Jobs\ExpireUnpaidStripeOrdersJob;
use App\Jobs\ProcessStripeRefundJob;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StripeWebhookEvent;
use App\Models\User;
use App\Models\UserVoucher;
use App\Models\Voucher;
use App\Services\InventoryService;
use App\Services\OrderCancellationService;
use App\Services\PaymentService;
use App\Services\StripeRefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Stripe\Refund;
use Tests\TestCase;

class CommerceFoundationTest extends TestCase
{
    use RefreshDatabase;

    private function createProduct(int $stock = 1, int $price = 10_000_000): Product
    {
        $category = Category::create(['name' => 'Laptop', 'slug' => 'laptop-' . uniqid()]);

        return Product::create([
            'name' => 'Test Laptop',
            'slug' => 'test-laptop-' . uniqid(),
            'price_display' => number_format($price, 0, ',', '.') . '₫',
            'image_main' => 'products/test.jpg',
            'stock' => $stock,
            'is_active' => true,
            'category_id' => $category->id,
        ]);
    }

    private function createCustomer(): User
    {
        return User::factory()->create(['is_admin' => false]);
    }

    private function createAdmin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function orderPayload(Product $product, int $price, int $qty = 1, ?int $variantId = null): array
    {
        $item = [
            'product_id' => $product->id,
            'product_name' => $product->name,
            'price' => $price,
            'quantity' => $qty,
        ];

        if ($variantId) {
            $item['product_variant_id'] = $variantId;
        }

        return [
            'full_name' => 'Nguyen Van A',
            'phone' => '0901234567',
            'address' => '123 Test Street',
            'to_district_id' => 1442,
            'to_ward_code' => '1A0101',
            'payment_method' => 'cod',
            'items' => [$item],
        ];
    }

    private function createVoucherForUser(User $user, string $code = 'TEST100K'): Voucher
    {
        $voucher = Voucher::create([
            'code' => $code,
            'title' => 'Test',
            'discount_type' => 'fixed',
            'discount_value' => 100_000,
            'min_order_amount' => 1_000_000,
            'is_active' => true,
            'used_count' => 0,
            'expires_at' => now()->addMonth(),
            'created_by' => $user->id,
        ]);

        UserVoucher::create([
            'user_id' => $user->id,
            'voucher_id' => $voucher->id,
            'reservation_status' => VoucherReservationStatus::Available->value,
            'saved_at' => now(),
        ]);

        return $voucher;
    }

    public function test_rejects_tampered_price_from_frontend(): void
    {
        $user = $this->createCustomer();
        $product = $this->createProduct(stock: 5, price: 10_000_000);
        Sanctum::actingAs($user);

        $payload = $this->orderPayload($product, 1_000_000);

        $this->postJson('/api/v1/orders', $payload)
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Giá sản phẩm "Test Laptop" đã thay đổi. Vui lòng tải lại trang.']);
    }

    public function test_last_item_cannot_be_sold_twice(): void
    {
        $product = $this->createProduct(stock: 1, price: 10_000_000);
        $user1 = $this->createCustomer();
        $user2 = User::factory()->create(['is_admin' => false]);
        $payload = $this->orderPayload($product, 10_000_000);

        Sanctum::actingAs($user1);
        $this->postJson('/api/v1/orders', $payload)->assertCreated();

        Sanctum::actingAs($user2);
        $this->postJson('/api/v1/orders', $payload)
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Sản phẩm "Test Laptop" chỉ còn 0 sản phẩm.']);
    }

    public function test_double_cancel_does_not_restore_stock_twice(): void
    {
        $user = $this->createCustomer();
        $product = $this->createProduct(stock: 2, price: 10_000_000);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/orders', $this->orderPayload($product, 10_000_000));
        $orderId = $response->json('order_id');

        $this->patchJson("/api/v1/orders/{$orderId}/cancel")->assertOk();
        $this->patchJson("/api/v1/orders/{$orderId}/cancel")->assertStatus(422);

        $product->refresh();
        $this->assertSame(2, $product->stock);
    }

    public function test_expired_stripe_order_releases_stock_once(): void
    {
        $user = $this->createCustomer();
        $product = $this->createProduct(stock: 3, price: 10_000_000);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/orders', array_merge(
            $this->orderPayload($product, 10_000_000),
            ['payment_method' => 'stripe']
        ))->assertCreated();

        $order = Order::find($response->json('order_id'));
        $order->update(['expires_at' => now()->subMinute()]);

        (new ExpireUnpaidStripeOrdersJob)->handle(app(OrderCancellationService::class));

        $product->refresh();
        $order->refresh();

        $this->assertSame(3, $product->stock);
        $this->assertSame('cancelled', $order->order_status);
        $this->assertSame('expired', $order->payment_status);
        $this->assertNotNull($order->inventory_released_at);
    }

    public function test_voucher_cannot_be_reserved_for_two_orders(): void
    {
        $user = $this->createCustomer();
        $product = $this->createProduct(stock: 10, price: 12_000_000);
        $this->createVoucherForUser($user);

        Sanctum::actingAs($user);
        $payload = array_merge($this->orderPayload($product, 12_000_000), [
            'voucher_code' => 'TEST100K',
        ]);

        $this->postJson('/api/v1/orders', $payload)->assertCreated();
        $this->postJson('/api/v1/orders', $payload)
            ->assertStatus(422)
            ->assertJsonFragment(['message' => 'Voucher đang được giữ cho đơn hàng khác.']);
    }

    public function test_webhook_claim_and_process_lifecycle(): void
    {
        $service = app(PaymentService::class);

        $claim = $service->claimWebhookEvent('evt_test_1', 'payment_intent.succeeded');
        $this->assertNotNull($claim);
        $this->assertSame('processing', $claim->event->status);
        $this->assertNotNull($claim->event->processing_owner);
        $this->assertNull($claim->event->processed_at);

        $this->assertNull($service->claimWebhookEvent('evt_test_1', 'payment_intent.succeeded'));

        $service->markWebhookProcessed($claim);
        $processed = StripeWebhookEvent::where('event_id', 'evt_test_1')->first();
        $this->assertSame('processed', $processed->status);
        $this->assertNotNull($processed->processed_at);
        $this->assertNull($processed->processing_owner);

        StripeWebhookEvent::create([
            'event_id' => 'evt_test_2',
            'type' => 'payment_intent.succeeded',
            'status' => 'failed',
            'error_message' => 'db error',
            'processed_at' => null,
        ]);

        $retry = $service->claimWebhookEvent('evt_test_2', 'payment_intent.succeeded');
        $this->assertNotNull($retry);
        $this->assertSame('processing', $retry->event->status);
    }

    public function test_webhook_lease_expired_allows_reclaim(): void
    {
        StripeWebhookEvent::create([
            'event_id' => 'evt_stale',
            'type' => 'payment_intent.succeeded',
            'status' => 'processing',
            'processing_owner' => 'old-owner',
            'processing_started_at' => now()->subMinutes(10),
            'processed_at' => null,
        ]);

        $service = app(PaymentService::class);
        $claim = $service->claimWebhookEvent('evt_stale', 'payment_intent.succeeded');

        $this->assertNotNull($claim);
        $this->assertNotSame('old-owner', $claim->owner);
        $this->assertTrue($claim->event->processing_started_at->isAfter(now()->subMinute()));
    }

    public function test_regular_user_cannot_access_admin_orders(): void
    {
        $user = $this->createCustomer();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/admin/orders')->assertForbidden();
    }

    public function test_duplicate_variant_lines_merge_and_deduct_stock_once(): void
    {
        $user = $this->createCustomer();
        $product = $this->createProduct(stock: 10, price: 10_000_000);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'group_key' => 'ram',
            'group_label' => 'RAM',
            'option_label' => '16GB',
            'stock' => 5,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        Sanctum::actingAs($user);

        $payload = $this->orderPayload($product, 10_000_000, 1, $variant->id);
        $payload['items'][] = [
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'product_name' => $product->name,
            'option_label' => '16GB',
            'price' => 10_000_000,
            'quantity' => 2,
        ];

        $response = $this->postJson('/api/v1/orders', $payload)->assertCreated();
        $order = Order::with('items')->find($response->json('order_id'));

        $this->assertCount(1, $order->items);
        $this->assertSame(3, $order->items->first()->quantity);

        $variant->refresh();
        $this->assertSame(2, $variant->stock);
    }

    public function test_release_stock_finds_soft_deleted_variant(): void
    {
        $user = $this->createCustomer();
        $product = $this->createProduct(stock: 10, price: 10_000_000);
        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'group_key' => 'ram',
            'group_label' => 'RAM',
            'option_label' => '16GB',
            'stock' => 4,
            'is_active' => true,
            'sort_order' => 0,
        ]);

        Sanctum::actingAs($user);
        $response = $this->postJson('/api/v1/orders', $this->orderPayload($product, 10_000_000, 2, $variant->id))
            ->assertCreated();

        $variant->refresh();
        $this->assertSame(2, $variant->stock);

        $variant->delete();
        $order = Order::find($response->json('order_id'));

        app(OrderCancellationService::class)->cancel($order);

        $variant = ProductVariant::withTrashed()->find($variant->id);
        $this->assertSame(4, $variant->stock);
    }

    public function test_cod_delivered_marks_voucher_used_and_creates_payment(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createCustomer();
        $product = $this->createProduct(stock: 10, price: 12_000_000);
        $voucher = $this->createVoucherForUser($user);

        Sanctum::actingAs($user);
        $response = $this->postJson('/api/v1/orders', array_merge(
            $this->orderPayload($product, 12_000_000),
            ['voucher_code' => $voucher->code]
        ))->assertCreated();

        $order = Order::find($response->json('order_id'));
        $userVoucher = UserVoucher::where('user_id', $user->id)->where('voucher_id', $voucher->id)->first();
        $this->assertSame(VoucherReservationStatus::Reserved->value, $userVoucher->reservation_status);

        Sanctum::actingAs($admin);
        $this->patchJson("/api/v1/admin/orders/{$order->id}/status", [
            'status' => 'processing',
        ])->assertOk();
        $this->patchJson("/api/v1/admin/orders/{$order->id}/status", [
            'status' => 'shipping',
        ])->assertOk();
        $this->patchJson("/api/v1/admin/orders/{$order->id}/status", [
            'status' => 'delivered',
        ])->assertOk();

        $order->refresh();
        $userVoucher->refresh();
        $voucher->refresh();

        $this->assertSame(PaymentStatus::Paid->value, $order->payment_status);
        $this->assertSame(VoucherReservationStatus::Used->value, $userVoucher->reservation_status);
        $this->assertSame(1, $voucher->used_count);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'provider' => 'cod',
            'status' => PaymentStatus::Paid->value,
        ]);
    }

    public function test_late_stripe_payment_records_requires_refund_and_dispatches_job(): void
    {
        Queue::fake();

        $user = $this->createCustomer();
        $product = $this->createProduct(stock: 5, price: 10_000_000);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/orders', array_merge(
            $this->orderPayload($product, 10_000_000),
            ['payment_method' => 'stripe']
        ))->assertCreated();

        $order = Order::find($response->json('order_id'));
        $order->update(['expires_at' => now()->subMinute()]);
        (new ExpireUnpaidStripeOrdersJob)->handle(app(OrderCancellationService::class));
        $order->refresh();

        $service = app(PaymentService::class);
        $result = $service->handleStripePaymentSuccess($order, 'pi_late_payment', 'test');

        $this->assertSame('requires_refund', $result);
        $order->refresh();

        $this->assertSame(PaymentStatus::RequiresRefund->value, $order->payment_status);
        $this->assertSame('cancelled', $order->order_status);
        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'provider' => 'stripe',
            'status' => PaymentStatus::RequiresRefund->value,
            'provider_reference' => 'pi_late_payment',
        ]);

        Queue::assertPushed(ProcessStripeRefundJob::class);
    }

    public function test_cannot_create_intent_when_order_already_paid(): void
    {
        $user = $this->createCustomer();
        $product = $this->createProduct(stock: 5, price: 10_000_000);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/orders', array_merge(
            $this->orderPayload($product, 10_000_000),
            ['payment_method' => 'stripe']
        ))->assertCreated();

        $order = Order::find($response->json('order_id'));
        $order->update([
            'payment_status' => PaymentStatus::Paid->value,
            'order_status' => 'confirmed',
            'stripe_payment_intent_id' => 'pi_existing',
        ]);

        $service = app(PaymentService::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Đơn đã thanh toán hoặc đang hoàn tiền.');
        $service->createStripeIntent($order);
    }

    public function test_duplicate_charge_on_paid_order_requires_refund(): void
    {
        Queue::fake();

        $user = $this->createCustomer();
        $product = $this->createProduct(stock: 5, price: 10_000_000);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/orders', array_merge(
            $this->orderPayload($product, 10_000_000),
            ['payment_method' => 'stripe']
        ))->assertCreated();

        $order = Order::find($response->json('order_id'));
        $order->update([
            'payment_status' => PaymentStatus::Paid->value,
            'order_status' => 'confirmed',
            'stripe_payment_intent_id' => 'pi_canonical',
        ]);

        \App\Models\Payment::create([
            'order_id' => $order->id,
            'provider' => 'stripe',
            'amount' => $order->subtotal,
            'currency' => 'vnd',
            'status' => PaymentStatus::Paid->value,
            'provider_reference' => 'pi_canonical',
            'idempotency_key' => "order:{$order->id}:payment:stripe:pi_canonical",
        ]);

        $service = app(PaymentService::class);
        $result = $service->handleStripePaymentSuccess($order, 'pi_duplicate', 'test');

        $this->assertSame('requires_refund', $result);
        $order->refresh();
        $this->assertSame(PaymentStatus::Paid->value, $order->payment_status);

        $this->assertDatabaseHas('payments', [
            'order_id' => $order->id,
            'provider_reference' => 'pi_duplicate',
            'status' => PaymentStatus::RequiresRefund->value,
        ]);

        Queue::assertPushed(ProcessStripeRefundJob::class);
    }

    public function test_admin_cannot_update_fulfillment_after_order_cancelled(): void
    {
        $admin = $this->createAdmin();
        $user = $this->createCustomer();
        $product = $this->createProduct(stock: 5, price: 10_000_000);

        Sanctum::actingAs($user);
        $response = $this->postJson('/api/v1/orders', $this->orderPayload($product, 10_000_000))
            ->assertCreated();

        $order = Order::find($response->json('order_id'));
        app(OrderCancellationService::class)->cancel($order);

        Sanctum::actingAs($admin);
        $this->patchJson("/api/v1/admin/orders/{$order->id}/status", [
            'status' => 'shipping',
        ])->assertStatus(422)
            ->assertJsonFragment(['message' => 'Đơn hàng đã hủy.']);

        $order->refresh();
        $this->assertSame('cancelled', $order->order_status);
        $this->assertSame('unfulfilled', $order->fulfillment_status);
    }

    public function test_aggregate_line_items_merges_same_sku(): void
    {
        $merged = InventoryService::aggregateLineItems([
            ['product_id' => 1, 'product_variant_id' => 2, 'quantity' => 1, 'price' => 100],
            ['product_id' => 1, 'product_variant_id' => 2, 'quantity' => 2, 'price' => 100],
        ]);

        $this->assertCount(1, $merged);
        $this->assertSame(3, $merged[0]['quantity']);
    }

    public function test_payment_intent_idempotency_key_is_stable_per_order(): void
    {
        $this->assertSame(
            'payment-intent-order-99-attempt-1',
            PaymentService::paymentIntentIdempotencyKey(99)
        );
        $this->assertSame(
            'payment-intent-order-99-attempt-3',
            PaymentService::paymentIntentIdempotencyKey(99, 3)
        );
    }

    public function test_refund_pending_schedules_delayed_recheck(): void
    {
        Queue::fake();

        $user = $this->createCustomer();
        $order = Order::create([
            'user_id' => $user->id,
            'order_status' => 'cancelled',
            'fulfillment_status' => 'unfulfilled',
            'full_name' => 'Test',
            'phone' => '0901234567',
            'address' => '123 Test',
            'items_subtotal' => 10_000_000,
            'subtotal' => 10_000_000,
            'shipping_fee' => 0,
            'order_code' => 'ORD-REFUND-PENDING',
            'payment_method' => 'stripe',
            'payment_status' => PaymentStatus::RequiresRefund->value,
        ]);

        $payment = \App\Models\Payment::create([
            'order_id' => $order->id,
            'provider' => 'stripe',
            'amount' => 10_000_000,
            'currency' => 'vnd',
            'status' => PaymentStatus::RequiresRefund->value,
            'provider_reference' => 'pi_pending_refund',
            'idempotency_key' => "order:{$order->id}:payment:stripe:pi_pending_refund",
        ]);

        $refund = Refund::constructFrom([
            'id' => 're_pending_test',
            'object' => 'refund',
            'status' => 'pending',
        ]);

        app(StripeRefundService::class)->persistRefundResult($payment, $refund);

        $payment->refresh();
        $this->assertSame('pending', $payment->meta['stripe_refund_status']);
        $this->assertSame(PaymentStatus::RequiresRefund->value, $payment->status);

        Queue::assertPushed(ProcessStripeRefundJob::class, fn ($job) => $job->paymentId === $payment->id);
    }

    public function test_reconcile_pending_refunds_dispatches_jobs(): void
    {
        Queue::fake();

        $user = $this->createCustomer();
        $order = Order::create([
            'user_id' => $user->id,
            'order_status' => 'cancelled',
            'fulfillment_status' => 'unfulfilled',
            'full_name' => 'Test',
            'phone' => '0901234567',
            'address' => '123 Test',
            'items_subtotal' => 10_000_000,
            'subtotal' => 10_000_000,
            'shipping_fee' => 0,
            'order_code' => 'ORD-RECONCILE',
            'payment_method' => 'stripe',
            'payment_status' => PaymentStatus::RequiresRefund->value,
        ]);

        $payment = \App\Models\Payment::create([
            'order_id' => $order->id,
            'provider' => 'stripe',
            'amount' => 10_000_000,
            'currency' => 'vnd',
            'status' => PaymentStatus::RequiresRefund->value,
            'provider_reference' => 'pi_reconcile',
            'idempotency_key' => "order:{$order->id}:payment:stripe:pi_reconcile",
        ]);

        $count = app(StripeRefundService::class)->reconcilePending();

        $this->assertSame(1, $count);
        Queue::assertPushed(ProcessStripeRefundJob::class, fn ($job) => $job->paymentId === $payment->id);
    }

    public function test_reconcile_skips_payments_over_max_refund_checks(): void
    {
        Queue::fake();

        $user = $this->createCustomer();
        $order = Order::create([
            'user_id' => $user->id,
            'order_status' => 'cancelled',
            'fulfillment_status' => 'unfulfilled',
            'full_name' => 'Test',
            'phone' => '0901234567',
            'address' => '123 Test',
            'items_subtotal' => 10_000_000,
            'subtotal' => 10_000_000,
            'shipping_fee' => 0,
            'order_code' => 'ORD-MAX-CHECKS',
            'payment_method' => 'stripe',
            'payment_status' => PaymentStatus::RequiresRefund->value,
        ]);

        \App\Models\Payment::create([
            'order_id' => $order->id,
            'provider' => 'stripe',
            'amount' => 10_000_000,
            'currency' => 'vnd',
            'status' => PaymentStatus::RequiresRefund->value,
            'provider_reference' => 'pi_max_checks',
            'idempotency_key' => "order:{$order->id}:payment:stripe:pi_max_checks",
            'meta' => ['refund_check_count' => 24],
        ]);

        $count = app(StripeRefundService::class)->reconcilePending();

        $this->assertSame(0, $count);
        Queue::assertNothingPushed();
    }

    public function test_refund_job_uses_unique_until_processing(): void
    {
        $this->assertInstanceOf(
            \Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing::class,
            new ProcessStripeRefundJob(1)
        );
    }
}
