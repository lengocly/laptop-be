<?php
namespace App\Jobs;
use App\Services\StripeRefundService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
class ReconcileStripeRefundsJob implements ShouldQueue
{
    use Queueable;
    public function handle(StripeRefundService $refundService): void
    {
        $count = $refundService->reconcilePending();
        if ($count > 0) {
            Log::info('ReconcileStripeRefundsJob dispatched refund checks', [
                'count' => $count,
            ]);
        }
    }
}

