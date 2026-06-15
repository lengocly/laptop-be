<?php

namespace App\Http\Controllers\Api;

use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\GhnShippingService;
use App\Services\InventoryService;
use App\Services\OrderCancellationService;
use App\Services\OrderStateMachine;
use App\Services\VoucherService;
use App\Support\PriceHelper;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function __construct(
        private VoucherService $voucherService,
        private InventoryService $inventoryService,
        private OrderCancellationService $cancellationService,
        private OrderStateMachine $stateMachine,
        private GhnShippingService $ghnShippingService,
    ) {}

    public function store(Request $request)
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'min:2', 'max:100'],
            'phone' => ['required', 'string', 'size:10', 'regex:/^0\d{9}$/'],
            'address' => ['required', 'string', 'min:5'],
            'note' => ['nullable', 'string'],
            'to_district_id' => ['required', 'integer', 'min:1'],
            'to_ward_code' => ['required', 'string', 'max:20'],
            'payment_method' => ['required', 'in:cod,stripe'],
            'voucher_id' => ['nullable', 'integer'],
            'voucher_code' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.product_variant_id' => ['nullable', 'integer'],
            'items.*.product_name' => ['required', 'string', 'max:255'],
            'items.*.option_label' => ['nullable', 'string', 'max:255'],
            'items.*.price' => ['required', 'integer', 'min:0'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
        ]);

        $order = DB::transaction(function () use ($request, $validated) {
            foreach ($validated['items'] as &$item) {
                $item['price'] = $this->resolveUnitPrice($item);
            }
            unset($item);

            $validated['items'] = InventoryService::aggregateLineItems($validated['items']);

            $itemsSubtotal = 0;

            foreach ($validated['items'] as $item) {
                $itemsSubtotal += $item['price'] * $item['quantity'];
            }

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

            $finalTotal = $itemsSubtotal - $voucherDiscount + $shippingFee;
            $isStripe = $validated['payment_method'] === 'stripe';

            $order = Order::create([
                'user_id' => $request->user()->id,
                'order_status' => $isStripe
                    ? OrderStatus::PendingPayment->value
                    : OrderStatus::Confirmed->value,
                'fulfillment_status' => FulfillmentStatus::Unfulfilled->value,
                'full_name' => $validated['full_name'],
                'phone' => $validated['phone'],
                'address' => $validated['address'],
                'to_district_id' => $validated['to_district_id'],
                'to_ward_code' => $validated['to_ward_code'],
                'note' => $validated['note'] ?? null,
                'items_subtotal' => $itemsSubtotal,
                'subtotal' => $finalTotal,
                'shipping_fee' => $shippingFee,
                'voucher_id' => $voucher?->id,
                'voucher_discount' => $voucherDiscount,
                'order_code' => $this->generateOrderCode(),
                'payment_method' => $validated['payment_method'],
                'payment_status' => PaymentStatus::Unpaid->value,
                'expires_at' => $isStripe
                    ? now()->addMinutes(config('commerce.stripe_order_expire_minutes', 30))
                    : null,
            ]);

            foreach ($validated['items'] as $item) {
                $unitPrice = $item['price'];
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

            $this->inventoryService->reserveForOrder($order, $validated['items']);

            if ($voucher && $userVoucher) {
                $this->voucherService->reserveForOrder($order, $userVoucher);
            }

            return $order;
        });

        return response()->json([
            'order_id' => $order->id,
            'order_code' => $order->order_code,
            'payment_method' => $order->payment_method,
            'subtotal' => $order->subtotal,
            'items_subtotal' => $order->items_subtotal,
            'shipping_fee' => $order->shipping_fee,
            'voucher_discount' => $order->voucher_discount,
            'order_status' => $order->order_status,
            'fulfillment_status' => $order->fulfillment_status,
            'status' => $order->status,
            'expires_at' => $order->expires_at,
            'message' => 'Đặt hàng thành công',
        ], 201);
    }

    public function index(Request $request)
    {
        $orders = Order::where('user_id', $request->user()->id)
            ->with('items')
            ->latest()
            ->get([
                'id',
                'order_code',
                'order_status',
                'fulfillment_status',
                'items_subtotal',
                'subtotal',
                'shipping_fee',
                'voucher_id',
                'voucher_discount',
                'payment_method',
                'payment_status',
                'full_name',
                'phone',
                'address',
                'note',
                'expires_at',
                'created_at',
            ]);

        return response()->json($orders);
    }

    public function cancel(Request $request, Order $order)
    {
        if ($order->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        try {
            $cancelled = $this->cancellationService->cancelByCustomer($order);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Đã hủy đơn hàng thành công.',
            'order' => $cancelled->load('items'),
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

    private function resolveShippingFee(
        int $toDistrictId,
        string $toWardCode,
        array $items,
        int $itemsSubtotal,
    ): int {
        if ($itemsSubtotal >= config('commerce.free_shipping_threshold', 10_000_000)) {
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
        throw new HttpResponseException(response()->json(['message' => $message], 422));
    }
}
