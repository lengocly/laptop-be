<?php

namespace App\Services;

use App\Models\Order;
use App\Models\UserVoucher;
use App\Models\Voucher;
use Illuminate\Http\Exceptions\HttpResponseException;

// Logic kiểm tra và tính giảm giá voucher — dùng chung cho checkout
class VoucherService
{
    /**
     * Tìm voucher hợp lệ cho user khi đặt hàng.
     * Nhận voucher_id hoặc voucher_code (một trong hai).
     */
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

        // Khóa voucher — tránh 2 đơn cùng dùng 1 mã voucher
        $voucher = $voucherId
            ? Voucher::lockForUpdate()->find($voucherId)
            : Voucher::lockForUpdate()->where('code', strtoupper(trim($voucherCode ?? '')))->first();

        if (!$voucher) {
            $this->fail('Voucher không tồn tại.');
        }

        $this->assertVoucherUsable($voucher, $itemsSubtotal);

        // Khóa bản ghi user_vouchers — tránh race condition lúc checkout
        $userVoucher = UserVoucher::where('user_id', $userId)
            ->where('voucher_id', $voucher->id)
            ->whereNull('used_at')
            ->lockForUpdate()
            ->first();

        if (!$userVoucher) {
            $this->fail('Bạn chưa lưu voucher này hoặc đã sử dụng.');
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

    // Kiểm tra voucher còn dùng được không (không cần user đã lưu)
    public function assertVoucherUsable(Voucher $voucher, int $itemsSubtotal): void
    {
        if (!$voucher->isCurrentlyValid()) {
            $this->fail('Voucher đã hết hạn hoặc không còn hiệu lực.');
        }

        if ($itemsSubtotal < $voucher->min_order_amount) {
            $this->fail(
                'Đơn hàng tối thiểu '
                . number_format($voucher->min_order_amount, 0, ',', '.') . 'đ để dùng voucher.'
            );
        }
    }

    // Đánh dấu voucher đã dùng — gọi sau khi thanh toán thành công
    public function markForOrder(Order $order): void
    {
        if (!$order->voucher_id) {
            return;
        }

        $userVoucher = UserVoucher::where('user_id', $order->user_id)
            ->where('voucher_id', $order->voucher_id)
            ->whereNull('used_at')
            ->lockForUpdate()
            ->first();

        if (!$userVoucher) {
            return;
        }

        $userVoucher->update(['used_at' => now()]);
        Voucher::where('id', $order->voucher_id)->increment('used_count');
    }

    // Hoàn voucher khi hủy đơn — chỉ khi đã đánh dấu used_at trước đó
    public function releaseForOrder(Order $order): void
    {
        if (!$order->voucher_id) {
            return;
        }

        $userVoucher = UserVoucher::where('user_id', $order->user_id)
            ->where('voucher_id', $order->voucher_id)
            ->whereNotNull('used_at')
            ->first();

        if ($userVoucher) {
            $userVoucher->update(['used_at' => null]);
        }

        $voucher = Voucher::find($order->voucher_id);

        // Giảm used_count nếu voucher đã được tính là đã dùng
        if ($voucher && $voucher->used_count > 0 && $userVoucher) {
            $voucher->decrement('used_count');
        }
    }

    private function fail(string $message): void
    {
        throw new HttpResponseException(response()->json([
            'message' => $message,
        ], 422));
    }
}
