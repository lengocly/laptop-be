<?php

use Illuminate\Support\Facades\Route;

//Tìm user theo id trong link
use App\Models\User;

// Sự kiện “email vừa được xác minh” (listener khác có thể bắt)
use Illuminate\Auth\Events\Verified;

//Kiểm tra chữ ký URL, lấy query expires, signature
use Illuminate\Http\Request;

Route::get('/', function () {
    return view('welcome');
});

//get: Chỉ đọc / mở link
Route::get('/email/verify/{id}/{hash}', function (Request $request, $id, $hash) {


    //Lấy URL frontend từ .env
    $frontend = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5173')), '/');

    
    // Mail gửi link có thời hạn (mặc định ~60 phút)
    if (! $request->hasValidSignature()) {
        return redirect($frontend . '/dang-nhap?error=invalid_or_expired_link');
    }

    //Lấy user theo id trong URL. Không có user → 404.
    $user = User::findOrFail($id);

    //Khi bấm link, so lại: đúng user + đúng email mới cho verify
    if (! hash_equals((string) sha1($user->getEmailForVerification()), (string) $hash)) {
        return redirect($frontend . '/dang-nhap?error=invalid_hash');
    }

    // Đánh dấu đã verify
    if (! $user->hasVerifiedEmail()) {
        $user->markEmailAsVerified();
        event(new Verified($user));
    }

    return redirect($frontend . '/dang-nhap?verified=1');
})->middleware('signed')->name('verification.verify');