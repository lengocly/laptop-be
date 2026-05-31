<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

//API Controller xử lý đặt hàng trong Laravel.
//khi frontend bấm “Xác nhận đặt hàng”, frontend sẽ gửi dữ liệu đơn hàng lên backend. File OrderController.php này sẽ nhận dữ liệu đó, kiểm tra hợp lệ, tạo đơn hàng trong bảng orders, tạo chi tiết sản phẩm trong bảng order_items, rồi trả kết quả về frontend.

class OrderController extends Controller
{
    //đặt hàng
    public function store(Request $request)
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'min:2', 'max:100'],
            'phone' => ['required', 'string', 'size:10','regex:/^0\d{9}$/'],
            'address' => ['required', 'string', 'min:5'],
            'note' => ['nullable', 'string'],
            //phương thức thanh toán
            'payment_method' => ['required', 'in:cod,stripe'],

            //items, phải là mảng, và ít nhất có 1 sản phẩm
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'], //Mỗi sản phẩm trong giỏ bắt buộc có product_id
            'items.*.product_variant_id' => ['nullable', 'integer'], //Biến thể sản phẩm có thể có hoặc không
            'items.*.product_name' => ['required', 'string', 'max:255'],
            'items.*.option_label' => ['nullable', 'string', 'max:255'],
            'items.*.price' => ['required', 'integer', 'min:0'], //Mỗi sản phẩm phải có giá, kiểu số nguyên, không được âm
            'items.*.quantity' => ['required', 'integer', 'min:1'], //Mỗi sản phẩm phải có số lượng, ít nhất là 1
        ]);

        //DB::transaction() nghĩa là: tất cả thao tác bên trong phải thành công hết thì mới lưu vào database.
        $order = DB::transaction(function () use ($request, $validated) {
            $subtotal = 0;

            //Tính tổng tiền đơn hàng (Ví dụ:
                // Casio 580: 650000 x 1 = 650000
                // Casio 570: 450000 x 2 = 900000
                // Tổng:
                // subtotal = 1.550.000)
            foreach ($validated['items'] as $item) {
                $subtotal += $item['price'] * $item['quantity'];
            }

            //tạo mã đơn hàng
                // vd: ORD     = tiền tố đơn hàng
                // 2605    = năm + tháng hiện tại
                // 00001   = số thứ tự đơn trong tháng đó
            $orderCode = 'ORD-' . now()->format('ym') . '-' . str_pad(
                Order::whereYear('created_at', now()->year)
                    ->whereMonth('created_at', now()->month)
                    ->count() + 1, //(Nếu tháng này đã có 7 đơn, đơn mới sẽ là đơn thứ 8)
                5, '0', STR_PAD_LEFT
            );

            //Tạo đơn hàng mới trong bảng orders
            $order = Order::create([
                'user_id' => $request->user()->id,
                'full_name' => $validated['full_name'],
                'phone' => $validated['phone'],
                'address' => $validated['address'],
                'note' => $validated['note'] ?? null,
                'subtotal' => $subtotal,
                'status' => 'pending',
                'order_code' => $orderCode,
                'payment_method' => $validated['payment_method'],
                'payment_status' => 'unpaid',
                'status' => 'pending',
            ]);

            //Tạo chi tiết sản phẩm trong bảng order_items
            foreach ($validated['items'] as $item) {
                //tạo sản phẩm con thuộc về đơn hàng này
                $order->items()->create([
                    'product_id' => $item['product_id'],
                    'product_variant_id' => $item['product_variant_id'] ?? null,
                    'product_name' => $item['product_name'],
                    'option_label' => $item['option_label'] ?? null,
                    'price' => $item['price'],
                    'quantity' => $item['quantity'],
                    'line_total' => $item['price'] * $item['quantity'],
                ]);
            }

            return $order;
        });

        //Trả kết quả về frontend
        return response()->json([
            'order_id' => $order->id,
            'order_code' => $order->order_code,
            'payment_method' => $order->payment_method,
            'subtotal' => $order->subtotal,
            'message' => 'Đặt hàng thành công',
        ], 201);
    }


    //lịch sử mua hàng
    public function index(Request $request)
    {
        $orders = Order::where('user_id', $request->user()->id)
            ->with('items')
            ->latest()
            ->get([
                'id',
                'order_code',
                'subtotal',
                'payment_method',
                'payment_status',
                'status',
                'full_name',
                'phone',
                'address',
                'created_at',
            ]);

        return response()->json($orders);
    }
}
