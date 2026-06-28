<?php
use App\Http\Controllers\DevProxyController;
use Illuminate\Support\Facades\Route;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
Route::get('/mobile-test', function () {
    return response(
        "Ket noi thanh cong!\n\nMo tren dien thoai:\nhttp://10.1.0.96:8000\n\n(Nhan http:// o dau URL)",
        200,
        ['Content-Type' => 'text/plain; charset=utf-8']
    );
});
Route::get('/email/verify/{id}/{hash}', function (Request $request, $id, $hash) {
    $frontend = rtrim(config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:5173')), '/');
    if (! $request->hasValidSignature()) {
        return redirect($frontend . '/?error=invalid_or_expired_link');
    }
    $user = User::findOrFail($id);
    if (! hash_equals((string) sha1($user->getEmailForVerification()), (string) $hash)) {
        return redirect($frontend . '/?error=invalid_hash');
    }
    if (! $user->hasVerifiedEmail()) {
        $user->markEmailAsVerified();
        event(new Verified($user));
    }
    return redirect($frontend . '/?verified=1');
})->middleware('signed')->name('verification.verify');
if (app()->environment('local')) {
    Route::any('/{path?}', DevProxyController::class)
        ->where('path', '^(?!email(?:/|$)).*');
} else {
    Route::get('/', function () {
        return view('welcome');
    });
}

