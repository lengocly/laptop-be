<?php
namespace App\Services;
use App\Enums\PaymentStatus;
use App\Jobs\ProcessStripeRefundJob;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Stripe\Refund;
use Stripe\Stripe;
class StripeRefundService
{
    public function process(Payment $payment): void
    {
        if ($payment->status === PaymentStatus::Refunded->value) {
            return;
        }
        if ($payment->status !== PaymentStatus::RequiresRefund->value) {
            return;
        }
        $secret = config('services.stripe.secret');
        if (!$secret) {
            throw new RuntimeException(
                "StripeRefundService: Stripe secret missing for payment {$payment->id}"
            );
        }
        Stripe::setApiKey($secret);
        $existingRefundId = $payment->meta['stripe_refund_id'] ?? null;
        $refund = $existingRefundId
            ? Refund::retrieve($existingRefundId)
            : Refund::create([
                'payment_intent' => $payment->provider_reference,
                'metadata' => [
                    'order_id' => (string) $payment->order_id,
                    'payment_id' => (string) $payment->id,
                ],
            ], [
                'idempotency_key' => "refund-payment-{$payment->id}",
            ]);
        $this->persistRefundResult($payment, $refund);
    }
    public function handleRefundWebhook(object $refund): void
    {
        $paymentId = (int) ($refund->metadata->payment_id ?? 0);
        if (!$paymentId) {
            return;
        }
        $payment = Payment::find($paymentId);
        if (!$payment || $payment->status !== PaymentStatus::RequiresRefund->value) {
            return;
        }
        $secret = config('services.stripe.secret');
        if (!$secret) {
            throw new RuntimeException('StripeRefundService: Stripe secret missing for webhook refund');
        }
        Stripe::setApiKey($secret);
        $fresh = Refund::retrieve($refund->id);
        $this->persistRefundResult($payment, $fresh);
    }
    public function reconcilePending(): int
    {
        $count = 0;
        $maxChecks = (int) config('commerce.refund_pending_max_checks', 24);
        Payment::query()
            ->where('status', PaymentStatus::RequiresRefund->value)
            ->where('provider', 'stripe')
            ->where(function ($query) use ($maxChecks) {
                $query->whereNull('meta')
                    ->orWhereNull('meta->refund_check_count')
                    ->orWhere('meta->refund_check_count', '<', $maxChecks);
            })
            ->orderBy('id')
            ->chunkById(50, function ($payments) use (&$count) {
                foreach ($payments as $payment) {
                    ProcessStripeRefundJob::dispatch($payment->id);
                    $count++;
                }
            });
        return $count;
    }
    public function isEligibleForScheduledReconcile(Payment $payment): bool
    {
        if ($payment->status !== PaymentStatus::RequiresRefund->value
            || $payment->provider !== 'stripe') {
            return false;
        }
        $maxChecks = (int) config('commerce.refund_pending_max_checks', 24);
        $checkCount = (int) ($payment->meta['refund_check_count'] ?? 0);
        return $checkCount < $maxChecks;
    }
    public function persistRefundResult(Payment $payment, Refund $refund): void
    {
        $stripeStatus = $refund->status ?? 'unknown';
        if ($stripeStatus === 'succeeded') {
            DB::transaction(function () use ($payment, $refund) {
                $locked = Payment::lockForUpdate()->find($payment->id);
                if (!$locked || $locked->status === PaymentStatus::Refunded->value) {
                    return;
                }
                $locked->update([
                    'status' => PaymentStatus::Refunded->value,
                    'meta' => array_merge($locked->meta ?? [], [
                        'stripe_refund_id' => $refund->id,
                        'stripe_refund_status' => 'succeeded',
                        'refunded_at' => now()->toIso8601String(),
                    ]),
                ]);
                Order::where('id', $locked->order_id)
                    ->where('payment_status', PaymentStatus::RequiresRefund->value)
                    ->update(['payment_status' => PaymentStatus::Refunded->value]);
            });
            return;
        }
        if ($stripeStatus === 'pending') {
            $checkCount = (int) ($payment->meta['refund_check_count'] ?? 0) + 1;
            $maxChecks = (int) config('commerce.refund_pending_max_checks', 24);
            Payment::where('id', $payment->id)
                ->where('status', PaymentStatus::RequiresRefund->value)
                ->update([
                    'meta' => array_merge($payment->meta ?? [], [
                        'stripe_refund_id' => $refund->id,
                        'stripe_refund_status' => 'pending',
                        'refund_requested_at' => $payment->meta['refund_requested_at'] ?? now()->toIso8601String(),
                        'refund_check_count' => $checkCount,
                        'last_refund_check_at' => now()->toIso8601String(),
                    ]),
                ]);
            Log::info('StripeRefundService: refund pending', [
                'payment_id' => $payment->id,
                'stripe_refund_id' => $refund->id,
                'check_count' => $checkCount,
            ]);
            if ($checkCount < $maxChecks) {
                $delayMinutes = (int) config('commerce.refund_reconcile_delay_minutes', 5);
                ProcessStripeRefundJob::dispatch($payment->id)
                    ->delay(now()->addMinutes($delayMinutes));
            } else {
                Log::warning('StripeRefundService: refund still pending after max checks', [
                    'payment_id' => $payment->id,
                    'stripe_refund_id' => $refund->id,
                ]);
            }
            return;
        }
        Payment::where('id', $payment->id)->update([
            'meta' => array_merge($payment->meta ?? [], [
                'stripe_refund_id' => $refund->id,
                'stripe_refund_status' => $stripeStatus,
                'refund_failed_at' => now()->toIso8601String(),
                'failure_reason' => $refund->failure_reason ?? null,
            ]),
        ]);
        throw new RuntimeException(
            "Stripe refund not succeeded (status={$stripeStatus}) for payment {$payment->id}"
        );
    }
}

