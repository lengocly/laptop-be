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
}
