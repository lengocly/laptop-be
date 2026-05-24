<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['name', 'slug', 'parent_id'];

    //Danh mục con: Một category có nhiều category con.
    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    //Danh mục cha: Một category có một category cha.
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    //Sản phẩm: Một category có nhiều sản phẩm.
    public function products()
    {
        return $this->hasMany(Product::class);
    }
}
