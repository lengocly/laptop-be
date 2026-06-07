<?php

namespace App\Services;

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

        $voucher = $voucherId
            ? Voucher::find($voucherId)
            : Voucher::where('code', strtoupper(trim($voucherCode ?? '')))->first();

        if (!$voucher) {
            $this->fail('Voucher không tồn tại.');
        }

        $this->assertVoucherUsable($voucher, $itemsSubtotal);

        // User phải đã lưu voucher và chưa dùng
        $userVoucher = UserVoucher::where('user_id', $userId)
            ->where('voucher_id', $voucher->id)
            ->whereNull('used_at')
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

    private function fail(string $message): void
    {
        throw new HttpResponseException(response()->json([
            'message' => $message,
        ], 422));
    }
}
