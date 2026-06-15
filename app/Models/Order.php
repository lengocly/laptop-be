<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// bảng thông tin đơn hàng
class Order extends Model
{
    protected $fillable = [
        'user_id',
        'full_name',
        'phone',
        'address',
        'note',
        'admin_note',
        'subtotal',
        'shipping_fee',
        'voucher_id',
        'voucher_discount',
        'status',
        'order_code',
        'payment_method',
        'payment_status',
        'stripe_payment_intent_id',
    ];

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function voucher()
    {
        return $this->belongsTo(Voucher::class);
    }
}
