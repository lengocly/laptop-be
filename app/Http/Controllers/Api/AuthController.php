<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        // Chuẩn hóa trước khi validate
        $request->merge([
            'name' => trim($request->name ?? ''), // xóa khoảng trắng ở đầu và cuối
            'email' => Str::lower(trim($request->email ?? '')), // chuyển email thành chữ thường
        ]);

        $validated = $request->validate([
            // Bắt buộc, chuỗi, tối thiểu 2 ký tự, tối đa 100 ký tự
            'name' => ['required', 'string', 'min:2', 'max:100'],

            //Email hợp lệ, chưa có trong bảng users, tối đa 100 ký tự 
            'email' => ['required', 'string', 'email:rfc,dns', 'max:100', 'unique:users,email'],

            //Bắt buộc; phải có password_confirmation trùng; tối thiểu 8 ký tự, ít nhất 1 chữ cái, 1 số
            'password' => ['required', 'confirmed',Password::min(8)->letters()->numbers(),
            ],
        ], [
            'name.min' => 'Họ tên phải có ít nhất 2 ký tự.',
            'name.regex' => 'Họ tên không được để trống.',
            'email.email' => 'Email không hợp lệ.',
            'email.unique' => 'Email này đã được đăng ký.',
            'password.min' => 'Mật khẩu phải có ít nhất 8 ký tự.',
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
        // Chuẩn hóa trước khi validate
        $request->merge([
            'email' => Str::lower(trim($request->email ?? '')),
        ]);

        // validate email và password
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8'],
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
        return response()->json($this->formatUser($request->user()));
    }

    //Cập nhật thông tin tài khoản
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $request->merge([
            'name' => trim($request->name ?? ''),
            'email' => Str::lower(trim($request->email ?? '')),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'string', 'email:rfc,dns', 'max:100', 'unique:users,email,'.$user->id],
        ], [
            'name.min' => 'Họ tên phải có ít nhất 2 ký tự.',
            'email.email' => 'Email không hợp lệ.',
            'email.unique' => 'Email này đã được sử dụng.',
        ]);

        $emailChanged = $user->email !== $validated['email'];

        $user->name = $validated['name'];
        $user->email = $validated['email'];

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
        }

        return response()->json([
            'message' => $emailChanged
                ? 'Cập nhật thành công. Vui lòng xác thực email mới.'
                : 'Cập nhật thông tin thành công.',
            'user' => $this->formatUser($user->fresh()),
        ]);
    }

    //Đổi mật khẩu
    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [
            'password.min' => 'Mật khẩu phải có ít nhất 8 ký tự.',
        ]);

        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Mật khẩu hiện tại không đúng.'],
            ]);
        }

        $user->password = $validated['password'];
        $user->save();

        return response()->json([
            'message' => 'Đổi mật khẩu thành công.',
        ]);
    }

    private function formatUser(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_admin' => (bool) $user->is_admin,
            'email_verified_at' => $user->email_verified_at,
        ];
    }
}