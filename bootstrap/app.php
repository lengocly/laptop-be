<?php

/**
 * =============================================================================
 * NHIỆM VỤ FILE NÀY (bootstrap/app.php)
 * =============================================================================
 * - Đây là điểm KHỞI TẠO ứng dụng Laravel: đăng ký route, middleware, xử lý lỗi.
 * - withRouting(...): báo cho Laravel biết file route nào dùng cho WEB, API, lệnh artisan.
 * - Dòng `api: .../routes/api.php`: mọi route trong api.php sẽ có tiền tố /api (mặc định),
 *   ví dụ route /v1/product trong api.php → URL đầy đủ: /api/v1/product
 *
 * Khi sửa file này: thường là thêm route file, middleware bảo vệ API, v.v.
 * =============================================================================
 */

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
