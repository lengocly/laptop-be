<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;

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
  });
});
