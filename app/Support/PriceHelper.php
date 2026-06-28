<?php
namespace App\Support;
class PriceHelper
{
    public static function parseDisplay(string $display): int
    {
        $digits = preg_replace('/\D/', '', $display);
        return (int) $digits;
    }
}

