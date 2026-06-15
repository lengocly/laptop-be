<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Jobs\ProcessStripeRefundJob;
use App\Models\Order;
use App\Models\Payment;
use App\Models\StripeWebhookEvent;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Stripe\PaymentIntent;
use Stripe\Stripe;

class PaymentService
{
    public function __construct(
        private VoucherService $voucherService,
    ) {}

    public function createStripeIntent(Order $order): PaymentIntent
    {
        if ($order->payment_method !== 'stripe') {
            throw new InvalidArgumentException('Đơn không dùng Stripe.');
        }

        Stripe::setApiKey(config('services.stripe.secret'));

        $pendingSyncIntentId = null;

        $intent = DB::transaction(function () use ($order, &$pendingSyncIntentId) {
            $fresh = Order::lockForUpdate()->findOrFail($order->id);
            $this->assertIntentCreatable($fresh);

            $attempt = (int) ($fresh->payment_attempt ?? 1);

            if ($fresh->stripe_payment_intent_id) {
                try {
                    $existing = PaymentIntent::retrieve($fresh->stripe_payment_intent_id);

                    if (in_array($existing->status, [
                        'requires_payment_method',
                        'requires_confirmation',
                        'requires_action',
                        'processing',
                    ], true)) {
                        return $existing;
                    }

                    if ($existing->status === 'succeeded') {
                        $pendingSyncIntentId = $existing->id;

                        return $existing;
                    }

                    if ($existing->status === 'canceled') {
                        $attempt++;
                        $fresh->update([
                            'payment_attempt' => $attempt,
                            'stripe_payment_intent_id' => null,
                        ]);
                    }
                } catch (\Throwable) {
                    // intent không còn trên Stripe — tạo lại với attempt hiện tại
                }
            }

            if ($pendingSyncIntentId) {
                return PaymentIntent::retrieve($pendingSyncIntentId);
            }

            $intent = PaymentIntent::create([
                'amount' => $fresh->subtotal,
                'currency' => 'vnd',
                'metadata' => [
                    'order_id' => $fresh->id,
                    'order_code' => $fresh->order_code,
                    'payment_attempt' => $attempt,
                ],
            ], [
                'idempotency_key' => self::paymentIntentIdempotencyKey($fresh->id, $attempt),
            ]);

            $fresh->update([
                'stripe_payment_intent_id' => $intent->id,
                'payment_attempt' => $attempt,
            ]);

            return $intent;
        });

        if ($pendingSyncIntentId) {
            $this->handleStripePaymentSuccess($order, $pendingSyncIntentId, 'intent_sync');
            $fresh = $order->fresh();

            if ($fresh->payment_status === PaymentStatus::Paid->value) {
                throw new InvalidArgumentException('Đơn đã thanh toán thành công.');
            }

            if ($fresh->payment_status === PaymentStatus::RequiresRefund->value) {
                throw new InvalidArgumentException(
                    'Thanh toán không thể áp dụng cho đơn này. Tiền sẽ được hoàn lại tự động.'
                );
            }

            throw new InvalidArgumentException(
                'Thanh toán đang được xử lý. Vui lòng tải lại trang sau vài giây.'
            );
        }

        return $intent;
    }

    public function markOrderPaid(Order $order, string $paymentIntentId, string $source = 'manual'): bool
    {
        $processed = false;

        DB::transaction(function () use ($order, $paymentIntentId, $source, &$processed) {
            $fresh = Order::lockForUpdate()->findOrFail($order->id);

            if ($fresh->payment_status === PaymentStatus::Paid->value) {
                return;
            }

            $this->assertPayableLocked($fresh);
            $this->applyStripePaid($fresh, $paymentIntentId, $source);
            $processed = true;
        });

        return $processed;
    }

    /**
     * Xử lý Stripe succeeded — trả về paid | already_paid | requires_refund.
     * requires_refund: ghi payment đối soát và dispatch job hoàn tiền.
     */
    public function handleStripePaymentSuccess(
        Order $order,
        string $paymentIntentId,
        string $source = 'webhook',
    ): string {
        try {
            if ($this->markOrderPaid($order, $paymentIntentId, $source)) {
                return 'paid';
            }
        } catch (InvalidArgumentException) {
            // Đơn không còn payable — ghi requires_refund bên dưới.
        }

        $fresh = $order->fresh();

        if ($fresh->payment_status === PaymentStatus::Paid->value) {
            if ($this->isSameRecordedIntent($fresh, $paymentIntentId)) {
                return 'already_paid';
            }

            $payment = $this->recordDuplicateStripePayment($fresh, $paymentIntentId, $source);
            ProcessStripeRefundJob::dispatch($payment->id);

            return 'requires_refund';
        }

        $payment = $this->recordLateStripePayment($fresh, $paymentIntentId, $source);
        ProcessStripeRefundJob::dispatch($payment->id);

        return 'requires_refund';
    }

    /** Ghi nhận thanh toán muộn trên đơn đã hết hạn/hủy — không nuốt mất tiền khách. */
    public function recordLateStripePayment(
        Order $order,
        string $paymentIntentId,
        string $source = 'webhook',
        ?string $reason = null,
    ): Payment {
        $payment = null;

        DB::transaction(function () use ($order, $paymentIntentId, $source, $reason, &$payment) {
            $fresh = Order::lockForUpdate()->findOrFail($order->id);

            if ($fresh->payment_status === PaymentStatus::Paid->value) {
                $payment = Payment::where('order_id', $fresh->id)
                    ->where('provider_reference', $paymentIntentId)
                    ->first();

                return;
            }

            $meta = [
                'source' => $source,
                'reason' => $reason ?? $this->unpayableReason($fresh),
            ];

            $payment = Payment::firstOrCreate(
                ['idempotency_key' => "order:{$fresh->id}:payment:stripe:{$paymentIntentId}"],
                [
                    'order_id' => $fresh->id,
                    'provider' => 'stripe',
                    'amount' => $fresh->subtotal,
                    'currency' => 'vnd',
                    'status' => PaymentStatus::RequiresRefund->value,
                    'provider_reference' => $paymentIntentId,
                    'meta' => $meta,
                ]
            );

            if ($payment->status !== PaymentStatus::RequiresRefund->value
                && $payment->status !== PaymentStatus::Refunded->value) {
                $payment->update([
                    'status' => PaymentStatus::RequiresRefund->value,
                    'meta' => array_merge($payment->meta ?? [], $meta),
                ]);
            }

            if (!in_array($fresh->payment_status, [
                PaymentStatus::Paid->value,
                PaymentStatus::RequiresRefund->value,
                PaymentStatus::Refunded->value,
            ], true)) {
                $fresh->update([
                    'stripe_payment_intent_id' => $paymentIntentId,
                    'payment_status' => PaymentStatus::RequiresRefund->value,
                ]);
            }
        });

        return $payment ?? Payment::where('idempotency_key', "order:{$order->id}:payment:stripe:{$paymentIntentId}")->firstOrFail();
    }

    /** Thanh toán trùng trên đơn đã paid — ghi payment requires_refund, không đổi trạng thái đơn. */
    public function recordDuplicateStripePayment(
        Order $order,
        string $paymentIntentId,
        string $source = 'webhook',
    ): Payment {
        $payment = null;

        DB::transaction(function () use ($order, $paymentIntentId, $source, &$payment) {
            $fresh = Order::lockForUpdate()->findOrFail($order->id);

            if ($fresh->payment_status !== PaymentStatus::Paid->value) {
                throw new InvalidArgumentException('Chỉ áp dụng khi đơn đã thanh toán.');
            }

            if ($this->isSameRecordedIntent($fresh, $paymentIntentId)) {
                $payment = Payment::where('order_id', $fresh->id)
                    ->where('provider_reference', $paymentIntentId)
                    ->first();

                return;
            }

            $meta = [
                'source' => $source,
                'reason' => 'duplicate_charge',
                'canonical_intent' => $fresh->stripe_payment_intent_id,
            ];

            $payment = Payment::firstOrCreate(
                ['idempotency_key' => "order:{$fresh->id}:payment:stripe:{$paymentIntentId}"],
                [
                    'order_id' => $fresh->id,
                    'provider' => 'stripe',
                    'amount' => $fresh->subtotal,
                    'currency' => 'vnd',
                    'status' => PaymentStatus::RequiresRefund->value,
                    'provider_reference' => $paymentIntentId,
                    'meta' => $meta,
                ]
            );

            if ($payment->status !== PaymentStatus::RequiresRefund->value
                && $payment->status !== PaymentStatus::Refunded->value) {
                $payment->update([
                    'status' => PaymentStatus::RequiresRefund->value,
                    'meta' => array_merge($payment->meta ?? [], $meta),
                ]);
            }
        });

        return $payment ?? Payment::where('idempotency_key', "order:{$order->id}:payment:stripe:{$paymentIntentId}")->firstOrFail();
    }

    /** COD giao thành công — ghi payment + đánh dấu voucher used. */
    public function markCodPaid(Order $order, string $source = 'cod_delivery'): bool
    {
        $processed = false;

        DB::transaction(function () use ($order, $source, &$processed) {
            $fresh = Order::lockForUpdate()->findOrFail($order->id);
            $this->applyCodPaid($fresh, $source);
            $processed = true;
        });

        return $processed;
    }

    /** Giả định đơn đã lockForUpdate trong transaction hiện tại. */
    public function applyCodPaid(Order $fresh, string $source = 'cod_delivery'): void
    {
        if ($fresh->payment_status === PaymentStatus::Paid->value) {
            return;
        }

        if ($fresh->payment_method !== 'cod') {
            throw new InvalidArgumentException('Đơn không phải COD.');
        }

        if ($fresh->order_status === OrderStatus::Cancelled->value) {
            throw new InvalidArgumentException('Đơn hàng đã hủy.');
        }

        $reference = "cod:{$fresh->order_code}";

        $fresh->update([
            'payment_status' => PaymentStatus::Paid->value,
            'order_status' => OrderStatus::Confirmed->value,
        ]);

        Payment::firstOrCreate(
            ['idempotency_key' => "order:{$fresh->id}:payment:cod"],
            [
                'order_id' => $fresh->id,
                'provider' => 'cod',
                'amount' => $fresh->subtotal,
                'currency' => 'vnd',
                'status' => PaymentStatus::Paid->value,
                'provider_reference' => $reference,
                'meta' => ['source' => $source],
            ]
        );

        $this->voucherService->markForOrder($fresh);
    }

    /**
     * Atomic claim với lease — tránh kẹt vĩnh viễn và xử lý đồng thời.
     */
    public function claimWebhookEvent(string $eventId, string $type, int $attempt = 0): ?WebhookClaim
    {
        $owner = (string) Str::uuid();
        $now = now();
        $leaseSeconds = (int) config('commerce.webhook_lease_seconds', 120);
        $leaseExpiredBefore = $now->copy()->subSeconds($leaseSeconds);

        try {
            return DB::transaction(function () use ($eventId, $type, $owner, $now, $leaseExpiredBefore) {
                $existing = StripeWebhookEvent::where('event_id', $eventId)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    if ($existing->status === 'processed') {
                        return null;
                    }

                    if (
                        $existing->status === 'processing'
                        && $existing->processing_started_at
                        && $existing->processing_started_at->isAfter($leaseExpiredBefore)
                    ) {
                        return null;
                    }

                    $existing->update([
                        'status' => 'processing',
                        'processing_owner' => $owner,
                        'processing_started_at' => $now,
                        'processed_at' => null,
                        'error_message' => null,
                    ]);

                    return new WebhookClaim($existing->fresh(), $owner);
                }

                $event = StripeWebhookEvent::create([
                    'event_id' => $eventId,
                    'type' => $type,
                    'status' => 'processing',
                    'processing_owner' => $owner,
                    'processing_started_at' => $now,
                    'processed_at' => null,
                ]);

                return new WebhookClaim($event, $owner);
            });
        } catch (QueryException) {
            if ($attempt >= 2) {
                return null;
            }

            return $this->claimWebhookEvent($eventId, $type, $attempt + 1);
        }
    }

    public function markWebhookProcessed(WebhookClaim $claim, ?array $meta = null): void
    {
        $updated = StripeWebhookEvent::where('id', $claim->event->id)
            ->where('processing_owner', $claim->owner)
            ->where('status', 'processing')
            ->update([
                'status' => 'processed',
                'processed_at' => now(),
                'error_message' => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
                'processing_owner' => null,
                'processing_started_at' => null,
            ]);

        if (!$updated) {
            throw new InvalidArgumentException('Webhook lease lost before completion.');
        }
    }

    public function markWebhookFailed(WebhookClaim $claim, string $error): void
    {
        StripeWebhookEvent::where('id', $claim->event->id)
            ->where('processing_owner', $claim->owner)
            ->update([
                'status' => 'failed',
                'processed_at' => null,
                'error_message' => $error,
                'processing_owner' => null,
                'processing_started_at' => null,
            ]);
    }

    public function validateIntentForOrder(PaymentIntent $intent, Order $order): bool
    {
        return $intent->status === 'succeeded'
            && (int) ($intent->metadata->order_id ?? 0) === (int) $order->id
            && (int) $intent->amount === (int) $order->subtotal;
    }

    /** Idempotency key theo order + attempt — retry timeout an toàn, intent canceled tạo attempt mới. */
    public static function paymentIntentIdempotencyKey(int $orderId, int $attempt = 1): string
    {
        return "payment-intent-order-{$orderId}-attempt-{$attempt}";
    }

    private function applyStripePaid(Order $fresh, string $paymentIntentId, string $source): void
    {
        $fresh->update([
            'stripe_payment_intent_id' => $paymentIntentId,
            'payment_status' => PaymentStatus::Paid->value,
            'order_status' => OrderStatus::Confirmed->value,
            'expires_at' => null,
        ]);

        Payment::firstOrCreate(
            ['idempotency_key' => "order:{$fresh->id}:payment:stripe:{$paymentIntentId}"],
            [
                'order_id' => $fresh->id,
                'provider' => 'stripe',
                'amount' => $fresh->subtotal,
                'currency' => 'vnd',
                'status' => PaymentStatus::Paid->value,
                'provider_reference' => $paymentIntentId,
                'meta' => ['source' => $source],
            ]
        );

        $this->voucherService->markForOrder($fresh);
    }

    private function assertPayableLocked(Order $order): void
    {
        $this->assertIntentCreatable($order);
    }

    /** Chặn tạo intent / thanh toán khi đơn đã xử lý tiền. */
    private function assertIntentCreatable(Order $order): void
    {
        if (in_array($order->payment_status, [
            PaymentStatus::Paid->value,
            PaymentStatus::RequiresRefund->value,
            PaymentStatus::Refunded->value,
        ], true)) {
            throw new InvalidArgumentException('Đơn đã thanh toán hoặc đang hoàn tiền.');
        }

        if ($order->order_status === OrderStatus::Cancelled->value) {
            throw new InvalidArgumentException('Đơn hàng đã hủy, không thể thanh toán.');
        }

        if ($order->payment_status === PaymentStatus::Expired->value) {
            throw new InvalidArgumentException('Đơn hàng đã hết hạn thanh toán.');
        }

        if ($order->expires_at && $order->expires_at->isPast()) {
            throw new InvalidArgumentException('Đơn hàng đã hết hạn thanh toán.');
        }

        if ($order->inventory_released_at) {
            throw new InvalidArgumentException('Đơn hàng đã hoàn kho, không thể thanh toán.');
        }
    }

    private function isSameRecordedIntent(Order $order, string $paymentIntentId): bool
    {
        if ($order->stripe_payment_intent_id === $paymentIntentId) {
            return true;
        }

        return Payment::where('order_id', $order->id)
            ->where('provider_reference', $paymentIntentId)
            ->where('status', PaymentStatus::Paid->value)
            ->exists();
    }

    private function unpayableReason(Order $order): string
    {
        if ($order->order_status === OrderStatus::Cancelled->value) {
            return 'order_cancelled';
        }

        if ($order->payment_status === PaymentStatus::Expired->value
            || ($order->expires_at && $order->expires_at->isPast())) {
            return 'order_expired';
        }

        if ($order->inventory_released_at) {
            return 'inventory_released';
        }

        return 'order_not_payable';
    }
}
