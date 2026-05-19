<?php

/**
 * =============================================================================
 * NHIỆM VỤ FILE NÀY (Model Category)
 * =============================================================================
 * - Một dòng = một mục trên menu (Laptop, Phụ kiện, hoặc nhóm con như Chuột, Bàn phím).
 * - parent_id: NULL = cấp 1; có giá trị = con của danh mục cha (dùng cho dropdown).
 * - Quan hệ:
 *   - children(): các danh mục con (submenu).
 *   - products(): sản phẩm thuộc danh mục này.
 * =============================================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'parent_id',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'parent_id' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id');
    }
}
