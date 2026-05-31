<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;

//Gom các route API vào nhóm chung, mọi URL trong nhóm đều có thêm /v1 phía trước
Route::prefix('v1')->group(function () {

    Route::get('/ping', function () {
      return response()->json(['message' => 'API v1 OK']);
    });

    Route::get('/categories', [CategoryController::class, 'index']);

    //Controller xử lý — file ProductController.php
    Route::get('/products', [ProductController::class, 'index']);

    //Chi tiết sản phẩm
    Route::get('/products/{id}', [ProductController::class, 'show']);


    //Controller xử lý — file AuthController.php
    Route::post('/register', [AuthController::class, 'register']);

    //Đăng nhập
    Route::post('/login', [AuthController::class, 'login']);

    //Lấy thông tin user đang đăng nhập, đăng xuất
    Route::middleware('auth:sanctum')->group(function () {
      Route::get('/user', [AuthController::class, 'user']);
      Route::post('/logout', [AuthController::class, 'logout']);
      
      //Lịch sử mua hàng
      Route::get('/orders', [OrderController::class, 'index']);

      //Đặt hàng
      Route::post('/orders', [OrderController::class, 'store']);

      //==== Thanh toán Stripe ====
      // Frontend gọi route này để tạo phiên thanh toán Stripe cho đơn hàng.
      Route::post('/payment/intent', [PaymentController::class, 'createIntent']);

      //Frontend gọi route này sau khi thanh toán thành công để backend kiểm tra lại với Stripe rồi cập nhật đơn
      Route::post('/payment/confirm', [PaymentController::class, 'confirmPaid']); 
    });

    // webhook Stripe
    // Stripe gọi route này tự động khi có sự kiện thanh toán. Route này không đặt trong auth:sanctum, vì Stripe không đăng nhập tài khoản user của web bạn.
    Route::post('/payment/webhook', [PaymentController::class, 'webhook']);

});
