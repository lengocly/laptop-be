<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    // Model = đọc/ghi bảng products bằng PHP (Product::all(), Product::create([...])).
    protected $fillable = [
        'name', 'slug', 'price_display', 'price_original', 'image_main', 'image_hover',
        'cpu', 'ram', 'storage', 'screen', 'stock', 'is_active', 'category_id',
    ];

    //Nói với Laravel: mỗi sản phẩm thuộc một danh mục, Đọc danh mục từ SP đã có 
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    //Nói với Laravel: mỗi sản phẩm có nhiều biến thể, Đọc biến thể từ SP đã có 
    public function variants()
    {
        return $this->hasMany(ProductVariant::class)->where('is_active', true)->orderBy('sort_order');
        // sắp xếp danh sách biến thể theo cột sort_order.
    }
}
