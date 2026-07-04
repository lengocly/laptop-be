<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    // Danh sách người dùng (phân trang + tìm kiếm)
    public function index(Request $request)
    {
        $query = User::query()
            ->select(['id', 'name', 'email', 'is_admin', 'email_verified_at', 'created_at'])
            ->withCount('orders')
            ->orderByDesc('id');

        if ($request->filled('keyword')) {
            $keyword = '%' . $request->keyword . '%';
            $query->where(function ($q) use ($keyword) {
                $q->where('name', 'like', $keyword)
                    ->orWhere('email', 'like', $keyword);
            });
        }

        $perPage = min(max((int) $request->input('per_page', 10), 1), 50);

        return response()->json($query->paginate($perPage));
    }

    // Chi tiết người dùng + lịch sử đơn hàng
    public function show(User $user)
    {
        $user->loadCount('orders');

        $orders = $user->orders()
            ->select([
                'id',
                'order_code',
                'order_status',
                'fulfillment_status',
                'payment_status',
                'payment_method',
                'subtotal',
                'created_at',
            ])
            ->latest()
            ->get();

        $totalSpent = $user->orders()
            ->where('order_status', '!=', 'cancelled')
            ->sum('subtotal');

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_admin' => (bool) $user->is_admin,
                'email_verified_at' => $user->email_verified_at,
                'created_at' => $user->created_at,
                'orders_count' => $user->orders_count,
            ],
            'total_spent' => (int) $totalSpent,
            'orders' => $orders,
        ]);
    }
}
