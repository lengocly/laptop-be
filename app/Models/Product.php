<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes; 

    // Model = đọc/ghi bảng products bằng PHP
    protected $fillable = [
        'name',
        'slug',
        'price_display',
        'price_original',
        'image_main',
        'image_hover',
        'cpu',
        'ram',
        'storage',
        'screen',
        'stock',
        'is_active',
        'category_id',
    ];

    // Mỗi sản phẩm thuộc một danh mục
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    // một sản phẩm có thể có nhiều biến thể
    public function variants()
    {
        return $this->hasMany(ProductVariant::class)
            ->where('is_active', true)
            ->orderBy('sort_order');
    }

    // Dùng cho admin: lấy tất cả biến thể, kể cả biến thể đang ẩn
    public function allVariants()
    {
        return $this->hasMany(ProductVariant::class)
            ->orderBy('sort_order');
    }

    // một sản phẩm có nhiều đánh giá
    public function reviews()
    {
        return $this->hasMany(ProductReview::class);
    }
}