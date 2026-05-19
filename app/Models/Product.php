<?php

/**
 * =============================================================================
 * NHIỆM VỤ FILE NÀY (Eloquent Model: Product)
 * =============================================================================
 * - Model đại diện cho BẢNG `products` trong MySQL: một object Product ≈ một dòng trong bảng.
 * - Eloquent giúp: Product::create(), Product::query()->where(...)->get() thay vì viết SQL tay.
 *
 * $fillable:
 * - Liệt kê cột được phép gán hàng loạt (mass assignment) khi dùng Product::create([...])
 *   hoặc $product->update([...]) — bảo vệ khỏi ghi nhầm cột lạ từ request.
 *
 * casts():
 * - Ép kiểu khi đọc/ghi: is_active → boolean, stock → integer (tiện dùng trong PHP).
 *
 * Không có hàm “xử lý” riêng ở đây: logic lấy danh sách nằm ở ProductController.
 * =============================================================================
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'price_display',
        'image_main',
        'image_hover',
        'cpu',
        'ram',
        'storage',
        'screen',
        'stock',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'stock' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }
}
