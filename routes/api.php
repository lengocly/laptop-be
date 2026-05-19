<?php

/**
 * =============================================================================
 * NHIỆM VỤ FILE NÀY (routes/api.php)
 * =============================================================================
 * - Định nghĩa các URL API (JSON) cho frontend React hoặc ứng dụng khác.
 * - Laravel tự thêm tiền tố /api cho file này → không cần ghi /api trong Route.
 *
 * NHÓM prefix('v1'):
 * - Mọi route bên trong sẽ bắt đầu bằng /v1...
 * - URL đầy đủ khi chạy `php artisan serve`: http://127.0.0.1:8000/api/v1/product
 *
 * Route::get('/categories', ...):
 * - Trả cây danh mục (menu): CategoryController@index.
 *
 * Route::get('/product', ...):
 * - Danh sách sản phẩm; có thể lọc: /product?category=laptop-gaming (slug danh mục cấp 2).
 * =============================================================================
 */

use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\ProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/product', [ProductController::class, 'index']);
    Route::get('/product/{id}', [ProductController::class, 'show'])->whereNumber('id');
});
