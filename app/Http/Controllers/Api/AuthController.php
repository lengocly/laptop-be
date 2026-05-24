<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validated = $request->validate([
            // Bắt buộc, chuỗi, tối đa 255 ký tự
            'name' => ['required', 'string', 'max:255'],

            //Email hợp lệ, chưa có trong bảng users
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],

            //Bắt buộc; phải có password_confirmation trùng; tối thiểu 6 ký tự
            'password' => ['required', 'confirmed', Password::min(6)],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'], // casts 'hashed' tự hash
        ]);

        //Phát sự kiện “user mới vừa đăng ký”.
        event(new Registered($user));

        return response()->json([
            'message' => 'Đăng ký thành công. Vui lòng kiểm tra email để xác thực tài khoản.',
        ], 200);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        //Tìm user theo email
        //Không User::create — user phải đã đăng ký từ register
        $user = User::where('email', $credentials['email'])->first();

        //Hash::check: so mật khẩu plain từ FE với chuỗi đã hash trong DB (do cast 'password' => 'hashed' lúc register).
        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email hoặc mật khẩu không đúng.'],
            ]);
        }

        //Kiểm tra email đã được xác minh chưa
        if (! $user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Vui lòng xác thực email trước khi đăng nhập.',
            ], 403);
        }
        $token = $user->createToken('web')->plainTextToken;
        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    //Đăng xuất
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Đã đăng xuất.']);
    }

    //Lấy thông tin user đang đăng nhập
    public function user(Request $request)
    {
        return response()->json($request->user());
    }
}