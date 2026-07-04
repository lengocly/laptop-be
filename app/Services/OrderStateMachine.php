<?php

namespace App\Services;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use InvalidArgumentException;

class OrderStateMachine
{
    /** Trạng thái hiển thị legacy cho frontend (tương thích status cũ). */
    public function legacyStatus(Order $order): string
    {
        if ($order->order_status === OrderStatus::Cancelled->value) {
            return 'cancelled';
        }

        if ($order->order_status === OrderStatus::PendingPayment->value) {
            return 'pending_payment';
        }

        return match ($order->fulfillment_status) {
            FulfillmentStatus::Processing->value => 'processing',
            FulfillmentStatus::Shipping->value => 'shipping',
            FulfillmentStatus::Delivered->value => 'delivered',
            default => 'pending',
        };
    }

    //kiểm tra xem user có thể hủy đơn hàng không
    public function canCustomerCancel(Order $order): bool
    {
        if ($order->order_status === OrderStatus::Cancelled->value) {
            return false;
        }

        if ($order->payment_status === 'paid') {
            return false;
        }

        return in_array($order->order_status, [
            OrderStatus::PendingPayment->value,
            OrderStatus::Confirmed->value,
        ], true)
            && $order->fulfillment_status === FulfillmentStatus::Unfulfilled->value;
    }

    //kiểm tra xem user có thể chuyển trạng thái đơn hàng không
    public function assertFulfillmentTransition(Order $order, FulfillmentStatus $to): void
    {
        if ($order->order_status !== OrderStatus::Confirmed->value) {
            throw new InvalidArgumentException('Chỉ đơn đã xác nhận mới được cập nhật giao hàng.');
        }

        $from = FulfillmentStatus::from($order->fulfillment_status);

        $allowed = match ($from) {
            FulfillmentStatus::Unfulfilled => [FulfillmentStatus::Processing],
            FulfillmentStatus::Processing => [FulfillmentStatus::Shipping],
            FulfillmentStatus::Shipping => [FulfillmentStatus::Delivered],
            FulfillmentStatus::Delivered => [],
        };

        if (!in_array($to, $allowed, true)) {
            throw new InvalidArgumentException(
                "Không thể chuyển từ {$from->value} sang {$to->value}."
            );
        }
    }

    //kiểm tra xem user có thể thanh toán đơn hàng không
    public function assertCanPay(Order $order): void
    {
        if ($order->order_status === OrderStatus::Cancelled->value) {
            throw new InvalidArgumentException('Đơn hàng đã hủy.');
        }

        if ($order->payment_status === 'paid') {
            throw new InvalidArgumentException('Đơn đã thanh toán.');
        }

        if ($order->expires_at && $order->expires_at->isPast()) {
            throw new InvalidArgumentException('Đơn hàng đã hết hạn thanh toán.');
        }
    }
}
