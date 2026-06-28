<?php
namespace App\Enums;
enum FulfillmentStatus: string
{
    case Unfulfilled = 'unfulfilled';
    case Processing = 'processing';
    case Shipping = 'shipping';
    case Delivered = 'delivered';
    public function label(): string
    {
        return match ($this) {
            self::Unfulfilled => 'Chờ xử lý',
            self::Processing => 'Đang xử lý',
            self::Shipping => 'Đã giao cho vận chuyển',
            self::Delivered => 'Đã giao hàng',
        };
    }
}

