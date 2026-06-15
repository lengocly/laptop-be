<?php

namespace App\Services;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class OrderCancellationService
{
    public function __construct(
        private InventoryService $inventoryService,
        private VoucherService $voucherService,
        private OrderStateMachine $stateMachine,
    ) {}

    public function cancel(Order $order, ?string $reason = null): Order
    {
        return DB::transaction(function () use ($order, $reason) {
            $fresh = Order::lockForUpdate()->findOrFail($order->id);

            if ($fresh->order_status === OrderStatus::Cancelled->value) {
                return $fresh;
            }

            if ($fresh->payment_status === PaymentStatus::Paid->value) {
                throw new \InvalidArgumentException('Không thể hủy đơn đã thanh toán.');
            }

            $fresh->update([
                'order_status' => OrderStatus::Cancelled->value,
                'payment_status' => $fresh->expires_at && $fresh->expires_at->isPast()
                    ? PaymentStatus::Expired->value
                    : $fresh->payment_status,
                'admin_note' => $reason ? trim(($fresh->admin_note ? $fresh->admin_note . "\n" : '') . $reason) : $fresh->admin_note,
            ]);

            $this->inventoryService->releaseForOrder($fresh);
            $this->voucherService->releaseForOrder($fresh);

            return $fresh->fresh()->load('items');
        });
    }

    public function cancelByCustomer(Order $order): Order
    {
        if (!$this->stateMachine->canCustomerCancel($order)) {
            throw new \InvalidArgumentException('Không thể hủy đơn hàng này.');
        }

        return $this->cancel($order);
    }
}
