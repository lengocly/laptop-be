<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;

// Hoàn tồn kho khi hủy đơn — ngược lại trừ stock lúc đặt hàng
class OrderStockService
{
    public function releaseForOrder(Order $order): void
    {
        $order->loadMissing('items');

        foreach ($order->items as $item) {
            if ($item->product_variant_id) {
                // Có biến thể → cộng lại vào product_variants.stock
                ProductVariant::where('id', $item->product_variant_id)
                    ->increment('stock', $item->quantity);
            } else {
                // Không biến thể → cộng lại vào products.stock
                Product::where('id', $item->product_id)
                    ->increment('stock', $item->quantity);
            }
        }
    }
}
