<?php

/**
 * =============================================================================
 * NHIỆM VỤ FILE NÀY (routes/web.php)
 * =============================================================================
 * - Định nghĩa route cho giao diện WEB truyền thống (HTML/Blade), KHÔNG có tiền tố /api.
 * - Route::get('/', ...): khi truy cập http://127.0.0.1:8000/ Laravel trả view welcome.
 *
 * Dự án của bạn: frontend chính là React (Vite); file này ít dùng, API nằm ở routes/api.php.
 * =============================================================================
 */

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});
