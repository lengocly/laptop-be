<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PendingPayment = 'pending_payment';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Chờ thanh toán',
            self::Confirmed => 'Đã xác nhận',
            self::Cancelled => 'Đã hủy',
        };
    }
}
