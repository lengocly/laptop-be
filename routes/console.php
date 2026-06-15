<?php

use App\Jobs\ExpireUnpaidStripeOrdersJob;
use App\Jobs\ReconcileStripeRefundsJob;
use App\Services\StripeRefundService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('orders:expire-unpaid', function () {
    ExpireUnpaidStripeOrdersJob::dispatchSync();
    $this->info('Expired unpaid Stripe orders processed.');
})->purpose('Hủy đơn Stripe chưa thanh toán quá hạn và hoàn kho');

Artisan::command('payments:reconcile-refunds', function (StripeRefundService $refundService) {
    $count = $refundService->reconcilePending();
    $this->info("Dispatched refund reconciliation for {$count} payment(s).");
})->purpose('Đối soát các payment requires_refund với Stripe');

Schedule::job(new ExpireUnpaidStripeOrdersJob)->everyFiveMinutes();
Schedule::job(new ReconcileStripeRefundsJob)->everyFifteenMinutes();
