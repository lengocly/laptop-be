<?php

namespace App\Support;

class PriceHelper
{
    // "12.990.000₫" → 12990000 — chỉ giữ chữ số, bỏ dấu chấm/ký hiệu tiền
    public static function parseDisplay(string $display): int
    {
        $digits = preg_replace('/\D/', '', $display);

        return (int) $digits;
    }
}
