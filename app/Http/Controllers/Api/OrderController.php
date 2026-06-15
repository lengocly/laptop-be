<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\OrderStockService;
use App\Services\VoucherService;
use App\Services\GhnShippingService;
use App\Support\PriceHelper;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

//API Controller xử lý đặt hàng trong Laravel.
//khi frontend bấm “Xác nhận đặt hàng”, frontend sẽ gửi dữ liệu đơn hàng lên backend. File OrderController.php này sẽ nhận dữ liệu đó, kiểm tra hợp lệ, tạo đơn hàng trong bảng orders, tạo chi tiết sản phẩm trong bảng order_items, rồi trả kết quả về frontend.

class OrderController extends Controller
{
    private const FREE_SHIPPING_THRESHOLD = 10_000_000;

    public function __construct(
        private VoucherService $voucherService,
        private OrderStockService $orderStockService,
        private GhnShippingService $ghnShippingService,
    ) {}

    //đặt hàng
    public function store(Request $request)
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'min:2', 'max:100'],
            'phone' => ['required', 'string', 'size:10','regex:/^0\d{9}$/'],
            'address' => ['required', 'string', 'min:5'],
            'note' => ['nullable', 'string'],
            'to_district_id' => ['required', 'integer', 'min:1'],
            'to_ward_code' => ['required', 'string', 'max:20'],
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

            // Tính tổng tiền hàng (trước voucher) — giá lấy từ DB, không tin client
            foreach ($validated['items'] as $item) {
                $unitPrice = $this->resolveUnitPrice($item);
                $itemsSubtotal += $unitPrice * $item['quantity'];
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

            $shippingFee = $this->resolveShippingFee(
                $validated['to_district_id'],
                $validated['to_ward_code'],
                $validated['items'],
                $itemsSubtotal,
            );

            // Tổng thanh toán = tiền hàng - voucher + phí ship
            $finalSubtotal = $itemsSubtotal - $voucherDiscount + $shippingFee;

            // Kiểm tra và trừ tồn kho trước khi tạo đơn
            foreach ($validated['items'] as $item) {
                $this->reserveStockForItem($item);
            }

            $orderCode = $this->generateOrderCode();

            //Tạo đơn hàng mới trong bảng orders
            $order = Order::create([
                'user_id' => $request->user()->id,
                'full_name' => $validated['full_name'],
                'phone' => $validated['phone'],
                'address' => $validated['address'],
                'note' => $validated['note'] ?? null,
                'subtotal' => $finalSubtotal,
                'shipping_fee' => $shippingFee,
                'voucher_id' => $voucher?->id,
                'voucher_discount' => $voucherDiscount,
                'status' => 'pending',
                'order_code' => $orderCode,
                'payment_method' => $validated['payment_method'],
                'payment_status' => 'unpaid',
            ]);

            //Tạo chi tiết sản phẩm trong bảng order_items
            foreach ($validated['items'] as $item) {
                $unitPrice = $this->resolveUnitPrice($item);
                $order->items()->create([
                    'product_id' => $item['product_id'],
                    'product_variant_id' => $item['product_variant_id'] ?? null,
                    'product_name' => $item['product_name'],
                    'option_label' => $item['option_label'] ?? null,
                    'price' => $unitPrice,
                    'quantity' => $item['quantity'],
                    'line_total' => $unitPrice * $item['quantity'],
                ]);
            }

            // Voucher chỉ đánh dấu đã dùng sau khi thanh toán thành công (confirmPaid/webhook)

            return $order;
        });

        //Trả kết quả về frontend
        return response()->json([
            'order_id' => $order->id,
            'order_code' => $order->order_code,
            'payment_method' => $order->payment_method,
            'subtotal' => $order->subtotal,
            'shipping_fee' => $order->shipping_fee,
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
                'shipping_fee',
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

        // Không cho hủy đơn đã trả tiền — tránh mất tiền không hoàn lại
        if ($order->payment_status === 'paid') {
            return response()->json([
                'message' => 'Đơn đã thanh toán, không thể hủy.',
            ], 422);
        }

        DB::transaction(function () use ($order) {
            $order->update(['status' => 'cancelled']);
            $this->orderStockService->releaseForOrder($order);
            $this->voucherService->releaseForOrder($order);
        });

        return response()->json([
            'message' => 'Đã hủy đơn hàng thành công.',
            'order' => $order->fresh()->load('items'),
        ]);
    }

    private function generateOrderCode(): string
    {
        $prefix = 'ORD-' . now()->format('ym') . '-';

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $sequence = Order::whereYear('created_at', now()->year)
                ->whereMonth('created_at', now()->month)
                ->lockForUpdate()
                ->count() + 1 + $attempt;

            $code = $prefix . str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);

            if (!Order::where('order_code', $code)->exists()) {
                return $code;
            }
        }

        return $prefix . strtoupper(substr(uniqid(), -5));
    }

    /** Giá đơn vị chuẩn từ DB — từ chối nếu client gửi sai. */
    private function resolveUnitPrice(array $item): int
    {
        $product = Product::find($item['product_id']);

        if (!$product || !$product->is_active) {
            $this->failStock('Sản phẩm "' . $item['product_name'] . '" không còn khả dụng.');
        }

        $hasVariants = $product->variants()->exists();
        $variantId = $item['product_variant_id'] ?? null;

        if ($hasVariants && !$variantId) {
            $this->failStock('Vui lòng chọn cấu hình cho sản phẩm "' . $item['product_name'] . '".');
        }

        if ($variantId) {
            $variant = ProductVariant::where('product_id', $product->id)
                ->where('id', $variantId)
                ->where('is_active', true)
                ->first();

            if (!$variant) {
                $this->failStock('Biến thể sản phẩm không hợp lệ hoặc đã ngừng bán.');
            }

            $expected = PriceHelper::parseDisplay($variant->price_display ?? $product->price_display);
        } else {
            $expected = PriceHelper::parseDisplay($product->price_display);
        }

        if ($expected <= 0) {
            $this->failStock('Không xác định được giá sản phẩm "' . $item['product_name'] . '".');
        }

        if ((int) $item['price'] !== $expected) {
            $this->failStock('Giá sản phẩm "' . $item['product_name'] . '" đã thay đổi. Vui lòng tải lại trang.');
        }

        return $expected;
    }

    /**
     * Khóa và trừ tồn kho cho một dòng hàng.
     * Có biến thể → trừ product_variants.stock; không → trừ products.stock.
     */
    private function reserveStockForItem(array $item): void
    {
        $product = Product::lockForUpdate()->find($item['product_id']);

        if (!$product || !$product->is_active) {
            $this->failStock('Sản phẩm "' . $item['product_name'] . '" không còn khả dụng.');
        }

        $variantId = $item['product_variant_id'] ?? null;

        if ($product->variants()->exists() && !$variantId) {
            $this->failStock('Vui lòng chọn cấu hình cho sản phẩm "' . $item['product_name'] . '".');
        }

        if ($variantId) {
            $variant = ProductVariant::lockForUpdate()
                ->where('product_id', $product->id)
                ->where('id', $variantId)
                ->first();

            if (!$variant || !$variant->is_active) {
                $this->failStock('Biến thể sản phẩm không hợp lệ hoặc đã ngừng bán.');
            }

            if ($variant->stock < $item['quantity']) {
                $this->failStock(
                    'Sản phẩm "' . $item['product_name'] . '" chỉ còn ' . $variant->stock . ' sản phẩm.'
                );
            }

            $variant->decrement('stock', $item['quantity']);

            return;
        }

        if ($product->stock < $item['quantity']) {
            $this->failStock(
                'Sản phẩm "' . $item['product_name'] . '" chỉ còn ' . $product->stock . ' sản phẩm.'
            );
        }

        $product->decrement('stock', $item['quantity']);
    }

    private function resolveShippingFee(
        int $toDistrictId,
        string $toWardCode,
        array $items,
        int $itemsSubtotal,
    ): int {
        if ($itemsSubtotal >= self::FREE_SHIPPING_THRESHOLD) {
            return 0;
        }

        try {
            return $this->ghnShippingService->calculateFee(
                $toDistrictId,
                $toWardCode,
                $this->estimateCartWeightGram($items),
                $itemsSubtotal,
            );
        } catch (\RuntimeException $e) {
            $this->failStock('Không tính được phí vận chuyển: ' . $e->getMessage());
        }
    }

    private function estimateCartWeightGram(array $items): int
    {
        $gram = 0;
        foreach ($items as $item) {
            $gram += 1500 * $item['quantity'];
        }

        return max($gram, (int) config('ghn.default_weight', 1500));
    }

    private function failStock(string $message): void
    {
        throw new HttpResponseException(response()->json([
            'message' => $message,
        ], 422));
    }
}
