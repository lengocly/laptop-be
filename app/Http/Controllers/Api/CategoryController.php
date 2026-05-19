<?php

/**
 * =============================================================================
 * NHIỆM VỤ FILE NÀY (API CategoryController)
 * =============================================================================
 * - Trả danh sách danh mục dạng CÂY (cha → con) để React vẽ menu / dropdown.
 *
 * HÀM index():
 * - Lấy các category có parent_id = NULL (mục cấp 1: Laptop, Phụ kiện).
 * - eager load children: các mục cấp 2 (Gaming, Chuột, Bàn phím, ...).
 * - sort_order: thứ tự hiển thị (số nhỏ lên trước).
 *
 * URL: GET /api/v1/categories
 * =============================================================================
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $categories = Category::query()
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->with(['children' => function ($q): void {
                $q->orderBy('sort_order');
            }])
            ->get();

        return response()->json(['categories' => $categories]);
    }
}
