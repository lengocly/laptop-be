<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

// Admin CRUD voucher — tạo/sửa/xóa khuyến mãi
class AdminVoucherController extends Controller
{
    // Danh sách voucher (phân trang)
    public function index(Request $request)
    {
        $query = Voucher::query()->latest();

        if ($request->filled('keyword')) {
            $kw = '%' . $request->keyword . '%';
            $query->where(function ($q) use ($kw) {
                $q->where('code', 'like', $kw)
                    ->orWhere('title', 'like', $kw);
            });
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $perPage = min(max((int) $request->input('per_page', 10), 1), 50);

        return response()->json($query->paginate($perPage));
    }

    public function show(Voucher $voucher)
    {
        return response()->json($voucher);
    }

    // Tạo voucher mới
    public function store(Request $request)
    {
        $validated = $this->validatePayload($request);

        $voucher = Voucher::create([
            ...$validated,
            'code' => strtoupper($validated['code']),
            'created_by' => $request->user()->id,
        ]);

        return response()->json($voucher, 201);
    }

    public function update(Request $request, Voucher $voucher)
    {
        $validated = $this->validatePayload($request, $voucher->id);

        $voucher->update([
            ...$validated,
            'code' => strtoupper($validated['code']),
        ]);

        return response()->json($voucher);
    }

    public function destroy(Voucher $voucher)
    {
        $voucher->delete();

        return response()->json(['message' => 'Đã xóa voucher']);
    }

    // Bật/tắt hiển thị voucher
    public function toggleActive(Voucher $voucher)
    {
        $voucher->update(['is_active' => !$voucher->is_active]);

        return response()->json([
            'message' => $voucher->is_active ? 'Đã bật voucher' : 'Đã tắt voucher',
            'voucher' => $voucher,
        ]);
    }

    private function validatePayload(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('vouchers', 'code')->ignore($ignoreId),
            ],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'discount_type' => ['required', 'in:fixed,percent'],
            'discount_value' => ['required', 'integer', 'min:1'],
            'min_order_amount' => ['required', 'integer', 'min:0'],
            'max_discount' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['required', 'date'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['boolean'],
        ]);
    }
}
