<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserVoucher;
use App\Models\Voucher;
use App\Services\VoucherService;
use Illuminate\Http\Request;

// API voucher cho user — xem, lưu, danh sách đã lưu
class VoucherController extends Controller
{
    public function __construct(private VoucherService $voucherService) {}

    // GET /vouchers — voucher đang active (trang chủ)
    public function index(Request $request)
    {
        $userId = $request->user('sanctum')?->id;

        $vouchers = Voucher::query()
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('usage_limit')
                    ->orWhereColumn('used_count', '<', 'usage_limit');
            })
            ->latest()
            ->get()
            ->map(fn (Voucher $v) => $v->toPublicArray($userId));

        return response()->json(['vouchers' => $vouchers]);
    }

    // POST /vouchers/{voucher}/save — user lưu voucher vào tài khoản
    public function save(Request $request, Voucher $voucher)
    {
        if (!$voucher->isCurrentlyValid()) {
            return response()->json([
                'message' => 'Voucher đã hết hạn hoặc không còn hiệu lực.',
            ], 422);
        }

        $existing = UserVoucher::where('user_id', $request->user()->id)
            ->where('voucher_id', $voucher->id)
            ->first();

        if ($existing) {
            if ($existing->used_at) {
                return response()->json([
                    'message' => 'Bạn đã sử dụng voucher này rồi.',
                ], 422);
            }

            return response()->json([
                'message' => 'Voucher đã được lưu trước đó.',
                'voucher' => $voucher->toPublicArray($request->user()->id),
            ]);
        }

        UserVoucher::create([
            'user_id' => $request->user()->id,
            'voucher_id' => $voucher->id,
            'saved_at' => now(),
        ]);

        return response()->json([
            'message' => 'Đã lưu voucher thành công!',
            'voucher' => $voucher->toPublicArray($request->user()->id),
        ], 201);
    }

    // GET /me/vouchers — voucher đã lưu, chưa dùng
    public function myVouchers(Request $request)
    {
        $items = UserVoucher::with('voucher')
            ->where('user_id', $request->user()->id)
            ->whereNull('used_at')
            ->latest('saved_at')
            ->get()
            ->filter(fn (UserVoucher $uv) => $uv->voucher && $uv->voucher->isCurrentlyValid())
            ->map(function (UserVoucher $uv) use ($request) {
                $v = $uv->voucher;

                return [
                    'user_voucher_id' => $uv->id,
                    ...$v->toPublicArray($request->user()->id),
                ];
            })
            ->values();

        return response()->json(['vouchers' => $items]);
    }

    // POST /vouchers/validate — kiểm tra trước khi checkout (tuỳ chọn)
    public function validateVoucher(Request $request)
    {
        $validated = $request->validate([
            'subtotal' => ['required', 'integer', 'min:0'],
            'voucher_id' => ['nullable', 'integer'],
            'voucher_code' => ['nullable', 'string'],
        ]);

        $result = $this->voucherService->resolveForCheckout(
            $request->user()->id,
            $validated['subtotal'],
            $validated['voucher_id'] ?? null,
            $validated['voucher_code'] ?? null,
        );

        return response()->json([
            'valid' => true,
            'discount' => $result['discount'],
            'final_total' => $validated['subtotal'] - $result['discount'],
            'voucher' => $result['voucher']?->toPublicArray($request->user()->id),
        ]);
    }
}
