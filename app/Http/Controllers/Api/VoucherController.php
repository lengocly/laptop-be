<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\UserVoucher;
use App\Models\Voucher;
use App\Services\VoucherService;
use Illuminate\Http\Request;
class VoucherController extends Controller
{
    public function __construct(private VoucherService $voucherService) {}
    public function index(Request $request)
    {
        $userId = $request->user('sanctum')?->id;
        $vouchers = Voucher::query()
            ->with(['userVouchers' => function ($q) use ($userId) {
                if ($userId) {
                    $q->where('user_id', $userId);
                }
            }])
            ->where('is_active', true)
            ->where('expires_at', '>', now())
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->latest()
            ->get()
            ->map(fn (Voucher $v) => $v->toPublicArray($userId));
        return response()->json(['vouchers' => $vouchers]);
    }
    public function save(Request $request, Voucher $voucher)
    {
        if (!$voucher->isSaveable()) {
            return response()->json([
                'message' => 'Voucher đã hết hạn hoặc không còn hiệu lực.',
            ], 422);
        }
        $existing = UserVoucher::where('user_id', $request->user()->id)
            ->where('voucher_id', $voucher->id)
            ->first();
        if ($existing) {
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
    public function myVouchers(Request $request)
    {
        $items = UserVoucher::with('voucher')
            ->where('user_id', $request->user()->id)
            ->latest('saved_at')
            ->get()
            ->filter(fn (UserVoucher $uv) => $uv->voucher && $uv->voucher->isSaveable())
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
    public function validateVoucher(Request $request)
    {
        $validated = $request->validate([
            'subtotal' => ['required', 'integer', 'min:0'],
            'voucher_id' => ['nullable', 'integer', 'required_without:voucher_code'],
            'voucher_code' => ['nullable', 'string', 'required_without:voucher_id'],
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

