<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\AdminOrderController;
use App\Http\Controllers\Api\AdminProductController;
use App\Http\Controllers\Api\AdminVoucherController;
use App\Http\Controllers\Api\VoucherController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\ShippingController;
use App\Http\Controllers\Api\ImageSearchController;


//Gom các route API vào nhóm chung, mọi URL trong nhóm đều có thêm /v1 phía trước
Route::prefix('v1')->group(function () {

    Route::get('/ping', function () {
      return response()->json(['message' => 'API v1 OK']);
    });

    // Chatbot AI — tối đa 20 tin/phút/IP
    Route::middleware('throttle:20,1')->post('/chat', [ChatController::class, 'send']);

    Route::get('/categories', [CategoryController::class, 'index']);

    // GHN — địa chỉ + tính phí vận chuyển (proxy, không lộ token)
    Route::get('/shipping/provinces', [ShippingController::class, 'provinces']);
    Route::get('/shipping/districts', [ShippingController::class, 'districts']);
    Route::get('/shipping/wards', [ShippingController::class, 'wards']);
    Route::post('/shipping/calculate-fee', [ShippingController::class, 'calculateFee']);

    // Tìm kiếm sản phẩm theo ảnh
    Route::post('/search/by-image', [ImageSearchController::class, 'searchByImage']);

    //Controller xử lý — file ProductController.php
    Route::get('/products', [ProductController::class, 'index']);

    //Chi tiết sản phẩm
    Route::get('/products/{id}', [ProductController::class, 'show']);

    // Đánh giá sản phẩm (xem công khai)
    Route::get('/products/{product}/reviews', [ReviewController::class, 'index']);

    // Voucher công khai — trang chủ (không bắt buộc đăng nhập)
    Route::get('/vouchers', [VoucherController::class, 'index']);


    //Controller xử lý — file AuthController.php
    Route::middleware('throttle:5,1')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
    });

    //Lấy thông tin user đang đăng nhập, đăng xuất
    Route::middleware('auth:sanctum')->group(function () {

      //Lấy thông tin user đang đăng nhập, cập nhật thông tin user, cập nhật mật khẩu
      Route::get('/user', [AuthController::class, 'user']);
      Route::put('/user/profile', [AuthController::class, 'updateProfile']);
      Route::put('/user/password', [AuthController::class, 'updatePassword']);
      Route::post('/logout', [AuthController::class, 'logout']);
      
      //Lịch sử mua hàng
      Route::get('/orders', [OrderController::class, 'index']);

      //Đặt hàng
      Route::post('/orders', [OrderController::class, 'store']);

      // Voucher của user — lưu, xem đã lưu, kiểm tra trước checkout
      Route::get('/me/vouchers', [VoucherController::class, 'myVouchers']);
      Route::post('/vouchers/validate', [VoucherController::class, 'validateVoucher']);
      Route::post('/vouchers/{voucher}/save', [VoucherController::class, 'save']);

      //Khách hủy đơn (chỉ khi admin chưa xác nhận)
      Route::patch('/orders/{order}/cancel', [OrderController::class, 'cancel']);

      // Đánh giá sản phẩm (chỉ khách đã mua và nhận hàng)
      Route::get('/products/{product}/reviews/eligibility', [ReviewController::class, 'eligibility']);
      Route::post('/products/{product}/reviews', [ReviewController::class, 'store']);

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

          // ===== Admin voucher =====
          Route::get('/vouchers', [AdminVoucherController::class, 'index']);
          Route::post('/vouchers', [AdminVoucherController::class, 'store']);
          Route::get('/vouchers/{voucher}', [AdminVoucherController::class, 'show']);
          Route::put('/vouchers/{voucher}', [AdminVoucherController::class, 'update']);
          Route::delete('/vouchers/{voucher}', [AdminVoucherController::class, 'destroy']);
          Route::patch('/vouchers/{voucher}/toggle', [AdminVoucherController::class, 'toggleActive']);
      });
    });

    // webhook Stripe
    // Stripe gọi route này tự động khi có sự kiện thanh toán. Route này không đặt trong auth:sanctum, vì Stripe không đăng nhập tài khoản user của web bạn.
    Route::post('/payment/webhook', [PaymentController::class, 'webhook']);

});
