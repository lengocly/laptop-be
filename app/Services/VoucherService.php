<?php
namespace App\Services;
use App\Enums\VoucherReservationStatus;
use App\Models\Order;
use App\Models\UserVoucher;
use App\Models\Voucher;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
class VoucherService
{
    public function resolveForCheckout(
        int $userId,
        int $itemsSubtotal,
        ?int $voucherId = null,
        ?string $voucherCode = null
    ): array {
        if (!$voucherId && !$voucherCode) {
            return [
                'voucher' => null,
                'user_voucher' => null,
                'discount' => 0,
            ];
        }
        $voucher = $voucherId
            ? Voucher::lockForUpdate()->find($voucherId)
            : Voucher::lockForUpdate()->where('code', strtoupper(trim($voucherCode ?? '')))->first();
        if (!$voucher) {
            $this->fail('Voucher không tồn tại.');
        }
        $this->assertVoucherUsable($voucher, $itemsSubtotal);
        $userVoucher = UserVoucher::where('user_id', $userId)
            ->where('voucher_id', $voucher->id)
            ->lockForUpdate()
            ->first();
        if (!$userVoucher) {
            $this->fail('Bạn chưa lưu voucher này.');
        }
        $discount = $voucher->calculateDiscount($itemsSubtotal);
        if ($discount <= 0) {
            $this->fail(
                'Đơn hàng chưa đạt giá trị tối thiểu '
                . number_format($voucher->min_order_amount, 0, ',', '.') . 'đ.'
            );
        }
        return [
            'voucher' => $voucher,
            'user_voucher' => $userVoucher,
            'discount' => $discount,
        ];
    }
    public function assertVoucherUsable(Voucher $voucher, int $itemsSubtotal): void
    {
        if (!$voucher->isSaveable()) {
            $this->fail('Voucher đã hết hạn hoặc không còn hiệu lực.');
        }
        if (!$voucher->hasUsageRemaining()) {
            $this->fail('Voucher đã hết lượt sử dụng.');
        }
        if ($itemsSubtotal < $voucher->min_order_amount) {
            $this->fail(
                'Đơn hàng tối thiểu '
                . number_format($voucher->min_order_amount, 0, ',', '.') . 'đ để dùng voucher.'
            );
        }
    }
    public function reserveForOrder(Order $order, UserVoucher $userVoucher): void
    {
        if (!$order->voucher_id) {
            return;
        }
        $voucher = Voucher::lockForUpdate()->find($order->voucher_id);
        if (!$voucher) {
            $this->fail('Voucher không tồn tại.');
        }
        if (!$voucher->hasUsageRemaining()) {
            $this->fail('Voucher đã hết lượt sử dụng.');
        }
        $voucher->increment('used_count');
        UserVoucher::where('id', $userVoucher->id)
            ->update([
                'reservation_status' => VoucherReservationStatus::Reserved->value,
                'reserved_order_id' => $order->id,
                'reserved_at' => now(),
            ]);
    }
    public function markForOrder(Order $order): void
    {
        if (!$order->voucher_id) {
            return;
        }
        UserVoucher::where('user_id', $order->user_id)
            ->where('voucher_id', $order->voucher_id)
            ->update([
                'reservation_status' => VoucherReservationStatus::Available->value,
                'reserved_order_id' => null,
                'reserved_at' => null,
            ]);
    }
    public function releaseForOrder(Order $order): bool
    {
        if (!$order->voucher_id || $order->voucher_released_at) {
            return false;
        }
        return (bool) DB::transaction(function () use ($order) {
            $fresh = Order::lockForUpdate()->find($order->id);
            if (!$fresh->voucher_id || $fresh->voucher_released_at) {
                return false;
            }
            UserVoucher::where('user_id', $fresh->user_id)
                ->where('voucher_id', $fresh->voucher_id)
                ->lockForUpdate()
                ->update([
                    'reservation_status' => VoucherReservationStatus::Available->value,
                    'reserved_order_id' => null,
                    'reserved_at' => null,
                    'used_at' => null,
                ]);
            $voucher = Voucher::lockForUpdate()->find($fresh->voucher_id);
            if ($voucher && $voucher->used_count > 0) {
                $voucher->decrement('used_count');
            }
            $fresh->update(['voucher_released_at' => now()]);
            return true;
        });
    }
    private function fail(string $message): void
    {
        throw new HttpResponseException(response()->json(['message' => $message], 422));
    }
}

