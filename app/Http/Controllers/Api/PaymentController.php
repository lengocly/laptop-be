<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\VoucherService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Stripe\Exception\SignatureVerificationException;
use Stripe\PaymentIntent;
use Stripe\Stripe;
use Stripe\Webhook;
use UnexpectedValueException;

//API Controller xử lý thanh toán Stripe trong Laravel.
//khi frontend bấm “Thanh toán”, frontend sẽ gọi route này để tạo phiên thanh toán Stripe cho đơn hàng. File PaymentController.php này sẽ nhận dữ liệu đó, tạo phiên thanh toán Stripe, rồi trả kết quả về frontend.
class PaymentController extends Controller
{
    public function __construct(private VoucherService $voucherService) {}

    // tạo phiên thanh toán Stripe cho đơn hàng
    public function createIntent(Request $request)
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer'],
        ]);

        $order = Order::where('id', $validated['order_id'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        // Đơn đã hủy không tạo phiên thanh toán mới
        if ($order->status === 'cancelled') {
            return response()->json([
                'message' => 'Đơn hàng đã hủy, không thể thanh toán.',
            ], 422);
        }

        if ($order->payment_method !== 'stripe') {
            return response()->json([
                'message' => 'Đơn không dùng Stripe',
            ], 422);
        }

        if ($order->payment_status === 'paid') {
            return response()->json([
                'message' => 'Đơn đã thanh toán',
            ], 422);
        }

        // cấu hình Stripe
        Stripe::setApiKey(config('services.stripe.secret'));

        // tạo phiên thanh toán Stripe
        $intent = null;

        if ($order->stripe_payment_intent_id) {
            // kiểm tra phiên thanh toán Stripe cũ
            try {
                $oldIntent = PaymentIntent::retrieve($order->stripe_payment_intent_id);

                // kiểm tra trạng thái phiên thanh toán Stripe cũ
                if (in_array($oldIntent->status, [
                    'requires_payment_method',
                    'requires_confirmation',
                    'requires_action',
                    'processing',
                ])) {
                    $intent = $oldIntent;
                }
            }
            // nếu có lỗi thì không tạo phiên thanh toán Stripe mới
            catch (\Throwable $e) {
                $intent = null;
            }
        }

        // nếu không có phiên thanh toán Stripe cũ thì tạo phiên thanh toán Stripe mới
        if (!$intent) {
            $intent = PaymentIntent::create([
                'amount' => $order->subtotal,
                'currency' => 'vnd',
                'metadata' => [
                    'order_id' => $order->id,
                    'order_code' => $order->order_code,
                ],
            ]);

            $order->update([
                'stripe_payment_intent_id' => $intent->id,
            ]);
        }

        // trả kết quả về frontend
        return response()->json([
            'client_secret' => $intent->client_secret,
            'order_id' => $order->id,
            'order_code' => $order->order_code,
            'subtotal' => $order->subtotal,
        ]);
    }

    // sau khi thanh toán thành công để backend kiểm tra lại với Stripe rồi cập nhật đơn
    public function confirmPaid(Request $request)
    {
        // kiểm tra dữ liệu nhận được
        $validated = $request->validate([
            'order_id' => ['required', 'integer'],
            'payment_intent_id' => ['required', 'string'],
        ]);

        // lấy đơn hàng
        $order = Order::where('id', $validated['order_id'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        // Đơn đã hủy không xác nhận thanh toán
        if ($order->status === 'cancelled') {
            return response()->json([
                'message' => 'Đơn hàng đã hủy, không thể thanh toán.',
            ], 422);
        }

        // cấu hình Stripe
        Stripe::setApiKey(config('services.stripe.secret'));

        // lấy phiên thanh toán Stripe
        $intent = PaymentIntent::retrieve($validated['payment_intent_id']);

        // lấy mã đơn hàng từ metadata
        $metadataOrderId = $intent->metadata->order_id ?? null;

        // kiểm tra phiên thanh toán Stripe có hợp lệ không
        $isValidIntent =
            $intent->status === 'succeeded'
            && (int) $metadataOrderId === (int) $order->id
            && (int) $intent->amount === (int) $order->subtotal;

        if ($isValidIntent) {
            $this->markOrderPaid($order, $intent->id);

            // trả kết quả về frontend
            return response()->json([
                'message' => 'Thanh toán thành công',
                'order_id' => $order->id,
                'order_code' => $order->order_code,
                'payment_status' => $order->fresh()->payment_status,
                'status' => $order->fresh()->status,
            ]);
        }

        // Không lộ chi tiết Stripe cho client — tránh lộ thông tin giao dịch
        return response()->json([
            'message' => 'Thanh toán chưa thành công',
        ], 422);
    }

    // Stripe gọi route này tự động khi có sự kiện thanh toán. Route này không đặt trong auth:sanctum, vì Stripe không đăng nhập tài khoản user của web bạn.
    public function webhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $webhookSecret = config('services.stripe.webhook_secret');

        if (!$webhookSecret) {
            return response()->json(['message' => 'Webhook chưa cấu hình'], 500);
        }

        try {
            // Xác minh chữ ký — chỉ tin payload thật từ Stripe
            $event = Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
        } catch (UnexpectedValueException $e) {
            return response()->json(['message' => 'Payload không hợp lệ'], 400);
        } catch (SignatureVerificationException $e) {
            return response()->json(['message' => 'Chữ ký không hợp lệ'], 400);
        }

        if ($event->type === 'payment_intent.succeeded') {
            $intent = $event->data->object;
            $orderId = $intent->metadata->order_id ?? null;

            if ($orderId) {
                $order = Order::find($orderId);

                if (
                    $order
                    && (int) $intent->amount === (int) $order->subtotal
                ) {
                    $this->markOrderPaid($order, $intent->id);
                }
            }
        }

        return response()->json(['received' => true]);
    }

    // Cập nhật đơn đã trả tiền + đánh dấu voucher (idempotent)
    private function markOrderPaid(Order $order, string $paymentIntentId): void
    {
        if ($order->payment_status === 'paid' || $order->status === 'cancelled') {
            return;
        }

        DB::transaction(function () use ($order, $paymentIntentId) {
            $order->update([
                'stripe_payment_intent_id' => $paymentIntentId,
                'payment_status' => 'paid',
            ]);

            // Voucher chỉ tính đã dùng khi tiền đã vào
            $this->voucherService->markForOrder($order);
        });
    }
}
