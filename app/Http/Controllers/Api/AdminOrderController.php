<?php

// AdminOrderController.php: quản lý đơn hàng cho admin

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Order;

use App\Mail\OrderInvoiceMail;
use App\Services\OrderStockService;
use App\Services\VoucherService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class AdminOrderController extends Controller
{
    public function __construct(
        private OrderStockService $orderStockService,
        private VoucherService $voucherService,
    ) {}

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

                // Nếu muốn lưu note admin
        if (!empty($validated['note'])) {
            $data['admin_note'] = $validated['note'];
        }

         if (
            $validated['status'] === 'delivered'
            && $order->payment_method === 'cod'
            && $order->payment_status === 'unpaid' //chưa thu tiền
        ) {

            //Tự động thu tiền
            $data['payment_status'] = 'paid';
        }

        // Hủy đơn → hoàn kho + voucher trong transaction
        if ($validated['status'] === 'cancelled') {
            DB::transaction(function () use ($order, $data) {
                $order->update($data);
                $this->orderStockService->releaseForOrder($order);
                $this->voucherService->releaseForOrder($order);
            });
        } else {
            $order->update($data);
        }

        return response()->json([
            'message' => 'Cập nhật trạng thái đơn hàng thành công',
            'order' => $order->fresh()->load(['items', 'user:id,name,email']),
        ]);
     }

     //Admin xem chi tiết đơn hàng
     public function show(Order $order)
     {
         return response()->json(
             $order->load(['items', 'user:id,name,email'])
         );
     }
     
     //Admin hủy đơn hàng
     public function cancel(Order $order)
     {
         if (in_array($order->status, ['delivered', 'cancelled'], true)) {
             return response()->json([
                 'message' => 'Không thể hủy đơn đã giao hoặc đã hủy.',
             ], 422);
         }
     
         DB::transaction(function () use ($order) {
             $order->update(['status' => 'cancelled']);
             $this->orderStockService->releaseForOrder($order);
             $this->voucherService->releaseForOrder($order);
         });
     
         return response()->json([
             'message' => 'Đã hủy đơn hàng.',
             'order' => $order->fresh()->load(['items', 'user:id,name,email']),
         ]);
     }

     //Admin gửi hóa đơn qua email
     public function sendInvoice(Order $order)
    {
        $order->load(['items', 'user']);
        if (!$order->user?->email) {
            return response()->json(['message' => 'Khách không có email.'], 422);
        }
        Mail::to($order->user->email)->send(new OrderInvoiceMail($order));
        return response()->json([
            'message' => 'Hóa đơn đã được gửi qua email!',
        ]);
    }

    //Admin xem thống kê đơn hàng
    public function stats()
    {
        return response()->json([
            'total_orders' => Order::count(),
            'pending_count' => Order::where('status', 'pending')->count(),
            'revenue' => Order::where('payment_status', 'paid')->sum('subtotal'),
        ]);
    }

    //Admin xem thống kê doanh thu theo ngày
    public function revenueByDay(Request $request)
    {
        $days = min((int) $request->get('days', 7), 90);

        $rows = Order::where('payment_status', 'paid')
            ->where('created_at', '>=', now()->subDays($days - 1)->startOfDay())
            ->selectRaw('DATE(created_at) as date, SUM(subtotal) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // FE vẫn có thể fill 0 cho ngày thiếu, hoặc fill ở BE
        return response()->json(['days' => $days, 'data' => $rows]);
    }
}
