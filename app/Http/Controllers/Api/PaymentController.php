<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Stripe\PaymentIntent;
use Stripe\Stripe;

//API Controller xử lý thanh toán Stripe trong Laravel.
//khi frontend bấm “Thanh toán”, frontend sẽ gọi route này để tạo phiên thanh toán Stripe cho đơn hàng. File PaymentController.php này sẽ nhận dữ liệu đó, tạo phiên thanh toán Stripe, rồi trả kết quả về frontend.
class PaymentController extends Controller
{
    public function createIntent(Request $request)
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer'],
        ]);

        $order = Order::where('id', $validated['order_id'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

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
        $validated = $request->validate([
            'order_id' => ['required', 'integer'],
            'payment_intent_id' => ['required', 'string'],
        ]);

        $order = Order::where('id', $validated['order_id'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        Stripe::setApiKey(config('services.stripe.secret'));

        $intent = PaymentIntent::retrieve($validated['payment_intent_id']);

        $metadataOrderId = $intent->metadata->order_id ?? null;

        $isValidIntent =
            $intent->status === 'succeeded'
            && (int) $metadataOrderId === (int) $order->id
            && (int) $intent->amount === (int) $order->subtotal;

        if ($isValidIntent) {
            $order->update([
                'stripe_payment_intent_id' => $intent->id,
                'payment_status' => 'paid',
                'status' => 'paid',
            ]);

            return response()->json([
                'message' => 'Thanh toán thành công',
                'order_id' => $order->id,
                'order_code' => $order->order_code,
                'payment_status' => $order->payment_status,
                'status' => $order->status,
            ]);
        }

        return response()->json([
            'message' => 'Thanh toán chưa thành công',
            'stripe_status' => $intent->status,
            'stripe_amount' => $intent->amount,
            'order_subtotal' => $order->subtotal,
            'stripe_metadata_order_id' => $metadataOrderId,
            'order_id' => $order->id,
        ], 422);
    }

    // Stripe gọi route này tự động khi có sự kiện thanh toán. Route này không đặt trong auth:sanctum, vì Stripe không đăng nhập tài khoản user của web bạn.
    public function webhook(Request $request)
    {
        return response()->json([
            'received' => true,
        ]);
    }
}