<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

//biến thể sản phẩm: màu sắc, bộ nhớ, cấu hình, ...
class ProductVariant extends Model
{
    protected $fillable = [
        'product_id', 'group_key', 'group_label', 'option_label', 'sku',
        'price_display', 'price_original', 'stock', 'image_main',
        'sort_order', 'is_active',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}