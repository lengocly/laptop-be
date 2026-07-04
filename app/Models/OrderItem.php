<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// bảng lưu các sản phẩm nằm trong đơn hàng đó
class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'product_id',
        'product_variant_id',
        'product_name',
        'option_label',
        'price',
        'quantity',
        'line_total',
    ];

    //Một order item thuộc về một đơn hàng.
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
