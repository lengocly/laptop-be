<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class Product extends Model
{
    use SoftDeletes;
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
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
    public function variants()
    {
        return $this->hasMany(ProductVariant::class)
            ->where('is_active', true)
            ->orderBy('sort_order');
    }
    public function allVariants()
    {
        return $this->hasMany(ProductVariant::class)
            ->orderBy('sort_order');
    }
    public function reviews()
    {
        return $this->hasMany(ProductReview::class);
    }
}