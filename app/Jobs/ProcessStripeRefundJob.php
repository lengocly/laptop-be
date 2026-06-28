<?php
namespace App\Jobs;
use App\Models\Payment;
use App\Services\StripeRefundService;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
class ProcessStripeRefundJob implements ShouldQueue, ShouldBeUniqueUntilProcessing
{
    use Queueable;
    public int $tries = 5;
    public int $uniqueFor = 300;
    public function __construct(public int $paymentId) {}
    public function uniqueId(): string
    {
        return 'stripe-refund-' . $this->paymentId;
    }
    public function backoff(): array
    {
        return [60, 300, 900, 3600, 7200];
    }
    public function handle(StripeRefundService $refundService): void
    {
        $payment = Payment::find($this->paymentId);
        if (!$payment) {
            return;
        }
        $refundService->process($payment);
    }
    public function failed(?\Throwable $exception): void
    {
        Log::critical('ProcessStripeRefundJob permanently failed — payment stuck at requires_refund', [
            'payment_id' => $this->paymentId,
            'error' => $exception?->getMessage(),
        ]);
    }
}

