<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\AdminOrderController;
use App\Http\Controllers\Api\AdminProductController;

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

      //Khách hủy đơn (chỉ khi admin chưa xác nhận)
      Route::patch('/orders/{order}/cancel', [OrderController::class, 'cancel']);

      //==== Thanh toán Stripe ====
      // Frontend gọi route này để tạo phiên thanh toán Stripe cho đơn hàng.
      Route::post('/payment/intent', [PaymentController::class, 'createIntent']);

      //Frontend gọi route này sau khi thanh toán thành công để backend kiểm tra lại với Stripe rồi cập nhật đơn
      Route::post('/payment/confirm', [PaymentController::class, 'confirmPaid']); 


      // ===== ADMIN =====
      Route::middleware('admin')->prefix('admin')->group(function () {
        //Admin xem danh sách đơn hàng
          Route::get('/orders', [AdminOrderController::class, 'index']);
          //Admin đổi trạng thái giao hàng
          Route::patch('/orders/{order}/status', [AdminOrderController::class, 'updateStatus']);

          //Admin xem chi tiết đơn hàng
          Route::get('/orders/{order}', [AdminOrderController::class, 'show']);
          //Admin hủy đơn hàng
          Route::patch('/orders/{order}/cancel', [AdminOrderController::class, 'cancel']);

          //Admin gửi hóa đơn qua email
          Route::post('/orders/{order}/send-invoice', [AdminOrderController::class, 'sendInvoice']);

          //Admin xem thống kê doanh thu theo ngày
          Route::get('/stats/revenue-by-day', [AdminOrderController::class, 'revenueByDay']);

          
          //Admin xem danh sách sản phẩm
          Route::get('/products', [AdminProductController::class, 'index']);
          //Admin xem chi tiết sản phẩm
          Route::get('/products/{product}', [AdminProductController::class, 'show']);
          //Admin thêm sản phẩm
          Route::post('/products', [AdminProductController::class, 'store']);
          //Admin sửa sản phẩm
          Route::put('/products/{product}', [AdminProductController::class, 'update']);
          //Admin xóa sản phẩm
          Route::delete('/products/{product}', [AdminProductController::class, 'destroy']);

          //Admin upload ảnh sản phẩm
          Route::post('/products/upload-image', [AdminProductController::class, 'uploadImage']);
      });
    });

    // webhook Stripe
    // Stripe gọi route này tự động khi có sự kiện thanh toán. Route này không đặt trong auth:sanctum, vì Stripe không đăng nhập tài khoản user của web bạn.
    Route::post('/payment/webhook', [PaymentController::class, 'webhook']);

});
