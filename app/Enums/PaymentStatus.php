<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case Paid = 'paid';
    case Failed = 'failed';
    case Refunded = 'refunded';
    case Expired = 'expired';
    case RequiresRefund = 'requires_refund';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Chờ thanh toán',
            self::Paid => 'Đã thanh toán',
            self::Failed => 'Thanh toán thất bại',
            self::Refunded => 'Đã hoàn tiền',
            self::Expired => 'Hết hạn thanh toán',
            self::RequiresRefund => 'Cần hoàn tiền',
        };
    }
}
