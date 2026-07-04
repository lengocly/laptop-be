<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next)
    {
        //lấy user đang đăng nhập bằng Sanctum
        //nếu không phải admin thì trả lỗi
        if (!$request->user()?->is_admin) {
            return response()->json(['message' => 'Bạn không có quyền admin.'], 403);
        }
        return $next($request);
    }
}