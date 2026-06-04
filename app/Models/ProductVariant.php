<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

//biến thể sản phẩm: màu sắc, bộ nhớ, cấu hình, ...
class ProductVariant extends Model
{
    protected $fillable = [
        'product_id',
        'group_key',
        'group_label',
        'option_label',
        'sku',
        'price_display', //giá hiển thị
        'price_original', //giá gốc
        'stock', //số lượng
        'sort_order', //thứ tự
        'is_active', //trạng thái
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}