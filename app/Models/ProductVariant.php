<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
class ProductVariant extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'product_id',
        'group_key',
        'group_label',
        'option_label',
        'sku',
        'price_display',
        'price_original',
        'stock',
        'sort_order',
        'is_active',
    ];
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}