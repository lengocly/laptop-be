<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Enums\PaymentStatus;
use App\Services\PaymentService;
use App\Services\StripeRefundService;
use Illuminate\Http\Request;
use Stripe\Exception\SignatureVerificationException;
use Stripe\PaymentIntent;
use Stripe\Stripe;
use Stripe\Webhook;
use UnexpectedValueException;
class PaymentController extends Controller
{
    public function __construct(
        private PaymentService $paymentService,
        private StripeRefundService $stripeRefundService,
    ) {}
    public function createIntent(Request $request)
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer'],
        ]);
        $order = Order::where('id', $validated['order_id'])
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
        try {
            $intent = $this->paymentService->createStripeIntent($order);
        } catch (\InvalidArgumentException $e) {
            $fresh = $order->fresh();
            if ($fresh->payment_status === PaymentStatus::Paid->value) {
                return response()->json([
                    'message' => 'Thanh toán thành công',
                    'order_id' => $fresh->id,
                    'order_code' => $fresh->order_code,
                    'payment_status' => $fresh->payment_status,
                    'order_status' => $fresh->order_status,
                    'status' => $fresh->status,
                    'already_paid' => true,
                ]);
            }
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json([
            'client_secret' => $intent->client_secret,
            'order_id' => $order->id,
            'order_code' => $order->order_code,
            'subtotal' => $order->subtotal,
        ]);
    }
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
        if (!$this->paymentService->validateIntentForOrder($intent, $order)) {
            return response()->json(['message' => 'Thanh toán chưa thành công'], 422);
        }
        $result = $this->paymentService->handleStripePaymentSuccess(
            $order,
            $intent->id,
            'confirm'
        );
        if ($result === 'requires_refund') {
            return response()->json([
                'message' => 'Đơn hàng đã hết hạn hoặc bị hủy. Tiền sẽ được hoàn lại tự động.',
                'payment_status' => 'requires_refund',
            ], 422);
        }
        $fresh = $order->fresh();
        return response()->json([
            'message' => 'Thanh toán thành công',
            'order_id' => $fresh->id,
            'order_code' => $fresh->order_code,
            'payment_status' => $fresh->payment_status,
            'order_status' => $fresh->order_status,
            'status' => $fresh->status,
        ]);
    }
    public function webhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $webhookSecret = config('services.stripe.webhook_secret');
        if (!$webhookSecret) {
            return response()->json(['message' => 'Webhook chưa cấu hình'], 500);
        }
        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
        } catch (UnexpectedValueException) {
            return response()->json(['message' => 'Payload không hợp lệ'], 400);
        } catch (SignatureVerificationException) {
            return response()->json(['message' => 'Chữ ký không hợp lệ'], 400);
        }
        $claim = $this->paymentService->claimWebhookEvent($event->id, $event->type);
        if (!$claim) {
            return response()->json(['received' => true, 'duplicate' => true]);
        }
        try {
            $meta = [];
            if ($event->type === 'payment_intent.succeeded') {
                $intent = $event->data->object;
                $orderId = $intent->metadata->order_id ?? null;
                if ($orderId) {
                    $order = Order::find($orderId);
                    if (
                        $order
                        && ($intent->status ?? null) === 'succeeded'
                        && (int) ($intent->metadata->order_id ?? 0) === (int) $order->id
                        && (int) $intent->amount === (int) $order->subtotal
                    ) {
                        $result = $this->paymentService->handleStripePaymentSuccess(
                            $order,
                            $intent->id,
                            'webhook'
                        );
                        $meta['payment_result'] = $result;
                    }
                }
            }
            if (in_array($event->type, ['charge.refund.updated', 'refund.updated'], true)) {
                $this->stripeRefundService->handleRefundWebhook($event->data->object);
                $meta['refund_event'] = $event->type;
            }
            $this->paymentService->markWebhookProcessed($claim, $meta ?: null);
        } catch (\Throwable $e) {
            $this->paymentService->markWebhookFailed($claim, $e->getMessage());
            return response()->json(['message' => 'Webhook processing failed'], 500);
        }
        return response()->json(['received' => true]);
    }
}

