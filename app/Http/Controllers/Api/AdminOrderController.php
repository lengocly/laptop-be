<?php
namespace App\Http\Controllers\Api;
use App\Enums\FulfillmentStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Mail\OrderInvoiceMail;
use App\Models\Order;
use App\Services\OrderCancellationService;
use App\Services\OrderStateMachine;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
class AdminOrderController extends Controller
{
    public function __construct(
        private OrderCancellationService $cancellationService,
        private OrderStateMachine $stateMachine,
        private PaymentService $paymentService,
    ) {}
    public function index()
    {
        $orders = Order::with(['items', 'user:id,name,email'])
            ->latest()
            ->get();
        return response()->json($orders);
    }
    public function updateStatus(Request $request, Order $order)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:pending,processing,shipping,delivered,cancelled'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        if ($validated['status'] === 'cancelled') {
            try {
                $cancelled = $this->cancellationService->cancel(
                    $order,
                    $validated['note'] ?? null
                );
            } catch (InvalidArgumentException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
            return response()->json([
                'message' => 'Cập nhật trạng thái đơn hàng thành công',
                'order' => $cancelled->load(['items', 'user:id,name,email']),
            ]);
        }
        $target = match ($validated['status']) {
            'pending' => FulfillmentStatus::Unfulfilled,
            'processing' => FulfillmentStatus::Processing,
            'shipping' => FulfillmentStatus::Shipping,
            'delivered' => FulfillmentStatus::Delivered,
            default => null,
        };
        try {
            $updated = DB::transaction(function () use ($order, $target, $validated) {
                $fresh = Order::lockForUpdate()->findOrFail($order->id);
                if ($fresh->order_status === OrderStatus::Cancelled->value) {
                    throw new InvalidArgumentException('Đơn hàng đã hủy.');
                }
                $this->stateMachine->assertFulfillmentTransition($fresh, $target);
                if (
                    $target === FulfillmentStatus::Delivered
                    && $fresh->payment_method === 'cod'
                    && $fresh->payment_status === PaymentStatus::Unpaid->value
                ) {
                    $this->paymentService->applyCodPaid($fresh, 'admin_delivered');
                    $fresh->refresh();
                }
                $data = ['fulfillment_status' => $target->value];
                if (!empty($validated['note'])) {
                    $data['admin_note'] = $validated['note'];
                }
                $fresh->update($data);
                return $fresh->fresh()->load(['items', 'user:id,name,email']);
            });
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json([
            'message' => 'Cập nhật trạng thái đơn hàng thành công',
            'order' => $updated,
        ]);
    }
    public function show(Order $order)
    {
        return response()->json(
            $order->load(['items', 'user:id,name,email', 'payments'])
        );
    }
    public function cancel(Order $order)
    {
        if ($order->order_status === OrderStatus::Cancelled->value) {
            return response()->json(['message' => 'Đơn đã hủy.'], 422);
        }
        if ($order->fulfillment_status === FulfillmentStatus::Delivered->value) {
            return response()->json(['message' => 'Không thể hủy đơn đã giao.'], 422);
        }
        try {
            $cancelled = $this->cancellationService->cancel($order);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json([
            'message' => 'Đã hủy đơn hàng.',
            'order' => $cancelled->load(['items', 'user:id,name,email']),
        ]);
    }
    public function sendInvoice(Order $order)
    {
        $order->load(['items', 'user']);
        if (!$order->user?->email) {
            return response()->json(['message' => 'Khách không có email.'], 422);
        }
        Mail::to($order->user->email)->send(new OrderInvoiceMail($order));
        return response()->json(['message' => 'Hóa đơn đã được gửi qua email!']);
    }
    public function stats()
    {
        return response()->json([
            'total_orders' => Order::count(),
            'pending_count' => Order::where('fulfillment_status', FulfillmentStatus::Unfulfilled->value)
                ->where('order_status', OrderStatus::Confirmed->value)
                ->count(),
            'revenue' => Order::where('payment_status', PaymentStatus::Paid->value)->sum('subtotal'),
        ]);
    }
    public function revenueByDay(Request $request)
    {
        $days = min((int) $request->get('days', 7), 90);
        $rows = Order::where('payment_status', PaymentStatus::Paid->value)
            ->where('created_at', '>=', now()->subDays($days - 1)->startOfDay())
            ->selectRaw('DATE(created_at) as date, SUM(subtotal) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get();
        return response()->json(['days' => $days, 'data' => $rows]);
    }
}

