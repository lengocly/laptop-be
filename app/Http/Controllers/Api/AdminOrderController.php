<?php

// AdminOrderController.php: quản lý đơn hàng cho admin

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Order;

class AdminOrderController extends Controller
{
     // Danh sách tất cả đơn
     public function index()
     {
         $orders = Order::with(['items', 'user:id,name,email'])
             ->latest()
             ->get();
         return response()->json($orders);
     }
     // Admin đổi trạng thái giao hàng
     public function updateStatus(Request $request, Order $order)
     {
         $validated = $request->validate([
             'status' => ['required', 'in:pending,processing,shipping,delivered,cancelled'],
             'note' => ['nullable', 'string', 'max:500'], // nếu thêm cột admin_note
         ]);

         //Chuẩn bị dữ liệu update: Đang giao hàng
         $data = ['status' => $validated['status']];

         if (
            $validated['status'] === 'delivered'
            && $order->payment_method === 'cod'
            && $order->payment_status === 'unpaid' //chưa thu tiền
        ) {

            //Tự động thu tiền
            $data['payment_status'] = 'paid';
        }
        // Đã hủy → không auto paid
        if ($validated['status'] === 'cancelled') {
            // giữ payment_status như cũ
        }
        $order->update($data);
        return response()->json([
            'message' => 'Cập nhật trạng thái đơn hàng thành công',
            'order' => $order->fresh()->load(['items', 'user:id,name,email']),
        ]);
     }
}
