<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Services\VoucherService;
use Illuminate\Support\Facades\DB;

//API Controller xử lý đặt hàng trong Laravel.
//khi frontend bấm “Xác nhận đặt hàng”, frontend sẽ gửi dữ liệu đơn hàng lên backend. File OrderController.php này sẽ nhận dữ liệu đó, kiểm tra hợp lệ, tạo đơn hàng trong bảng orders, tạo chi tiết sản phẩm trong bảng order_items, rồi trả kết quả về frontend.

class OrderController extends Controller
{
    public function __construct(private VoucherService $voucherService) {}

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

            // Voucher tuỳ chọn — gửi voucher_id hoặc voucher_code
            'voucher_id' => ['nullable', 'integer'],
            'voucher_code' => ['nullable', 'string'],

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
            $itemsSubtotal = 0;

            //Tính tổng tiền hàng (trước voucher)
            foreach ($validated['items'] as $item) {
                $itemsSubtotal += $item['price'] * $item['quantity'];
            }

            // Kiểm tra và tính giảm giá voucher (nếu có)
            $voucherResult = $this->voucherService->resolveForCheckout(
                $request->user()->id,
                $itemsSubtotal,
                $validated['voucher_id'] ?? null,
                $validated['voucher_code'] ?? null,
            );

            $voucher = $voucherResult['voucher'];
            $userVoucher = $voucherResult['user_voucher'];
            $voucherDiscount = $voucherResult['discount'];

            // Tổng thanh toán = tiền hàng - giảm voucher
            $finalSubtotal = $itemsSubtotal - $voucherDiscount;

            //tạo mã đơn hàng
            $orderCode = 'ORD-' . now()->format('ym') . '-' . str_pad(
                Order::whereYear('created_at', now()->year)
                    ->whereMonth('created_at', now()->month)
                    ->count() + 1,
                5, '0', STR_PAD_LEFT
            );

            //Tạo đơn hàng mới trong bảng orders
            $order = Order::create([
                'user_id' => $request->user()->id,
                'full_name' => $validated['full_name'],
                'phone' => $validated['phone'],
                'address' => $validated['address'],
                'note' => $validated['note'] ?? null,
                'subtotal' => $finalSubtotal,
                'voucher_id' => $voucher?->id,
                'voucher_discount' => $voucherDiscount,
                'status' => 'pending',
                'order_code' => $orderCode,
                'payment_method' => $validated['payment_method'],
                'payment_status' => 'unpaid',
            ]);

            //Tạo chi tiết sản phẩm trong bảng order_items
            foreach ($validated['items'] as $item) {
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

            // Đánh dấu voucher đã dùng
            if ($voucher && $userVoucher) {
                $userVoucher->update(['used_at' => now()]);
                $voucher->increment('used_count');
            }

            return $order;
        });

        //Trả kết quả về frontend
        return response()->json([
            'order_id' => $order->id,
            'order_code' => $order->order_code,
            'payment_method' => $order->payment_method,
            'subtotal' => $order->subtotal,
            'voucher_discount' => $order->voucher_discount,
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
                'voucher_id',
                'voucher_discount',
                'payment_method',
                'payment_status',
                'status',
                'full_name',
                'phone',
                'address',
                'note',
                'created_at',
            ]);

        return response()->json($orders);
    }

    // Khách hủy đơn (chỉ khi admin chưa xác nhận)
    public function cancel(Request $request, Order $order)
    {
        // Chỉ đơn của chính user
        if ($order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Chỉ hủy được khi còn "Chờ xử lý"
        if ($order->status !== 'pending') {
            return response()->json([
                'message' => 'Đơn hàng đã được xác nhận, không thể hủy.',
            ], 422);
        }

        $order->update(['status' => 'cancelled']);

        return response()->json([
            'message' => 'Đã hủy đơn hàng thành công.',
            'order' => $order->fresh()->load('items'),
        ]);
    }
}
