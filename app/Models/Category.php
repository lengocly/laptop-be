<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use SoftDeletes;
    protected $fillable = ['name', 'slug', 'parent_id', 'image', 'sort_order', 'is_featured'];

    protected $casts = [
        'is_featured' => 'boolean',
    ];

    //Một danh mục cha có nhiều danh mục con.
    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    //Một danh mục con thuộc về một danh mục cha.
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    //Một danh mục có nhiều sản phẩm.
    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
