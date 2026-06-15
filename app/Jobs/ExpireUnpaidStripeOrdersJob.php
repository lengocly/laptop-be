<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\OrderCancellationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ExpireUnpaidStripeOrdersJob implements ShouldQueue
{
    use Queueable;

    public function handle(OrderCancellationService $cancellationService): void
    {
        Order::query()
            ->where('order_status', 'pending_payment')
            ->where('payment_method', 'stripe')
            ->where('payment_status', 'unpaid')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->whereNull('inventory_released_at')
            ->orderBy('id')
            ->chunkById(50, function ($orders) use ($cancellationService) {
                foreach ($orders as $order) {
                    try {
                        $cancellationService->cancel($order, 'Tự động hủy: hết hạn thanh toán Stripe.');
                    } catch (\Throwable $e) {
                        Log::warning('ExpireUnpaidStripeOrdersJob failed', [
                            'order_id' => $order->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });
    }
}
