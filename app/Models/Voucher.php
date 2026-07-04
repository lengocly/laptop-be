<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Model voucher — khuyến mãi do admin tạo
class Voucher extends Model
{
    protected $fillable = [
        'code',
        'title',
        'description',
        'discount_type',
        'discount_value',
        'min_order_amount',
        'max_discount',
        'starts_at',
        'expires_at',
        'usage_limit',
        'used_count',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function userVouchers(): HasMany
    {
        return $this->hasMany(UserVoucher::class);
    }

    // Voucher còn hiệu lực để hiển thị / áp dụng?
    public function isCurrentlyValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        $now = now();

        if ($this->starts_at && $now->lt($this->starts_at)) {
            return false;
        }

        if ($now->gt($this->expires_at)) {
            return false;
        }

        if ($this->usage_limit !== null && $this->used_count >= $this->usage_limit) {
            return false;
        }

        return true;
    }

    // Tính số tiền giảm trên subtotal (VNĐ)
    public function calculateDiscount(int $subtotal): int
    {
        if ($subtotal < $this->min_order_amount) {
            return 0;
        }

        $discount = 0;

        if ($this->discount_type === 'fixed') {
            $discount = (int) $this->discount_value;
        } else {
            // percent
            $discount = (int) floor($subtotal * $this->discount_value / 100);
            if ($this->max_discount !== null) {
                $discount = min($discount, (int) $this->max_discount);
            }
        }

        // Không giảm quá tổng đơn
        return min($discount, $subtotal);
    }

    // Format JSON trả về frontend
    public function toPublicArray(?int $userId = null): array
    {
        $saved = false;
        if ($userId) {
            $saved = $this->userVouchers()
                ->where('user_id', $userId)
                ->whereNull('used_at')
                ->exists();
        }

        return [
            'id' => $this->id,
            'code' => $this->code,
            'title' => $this->title,
            'description' => $this->description,
            'discount_type' => $this->discount_type,
            'discount_value' => $this->discount_value,
            'min_order_amount' => $this->min_order_amount,
            'max_discount' => $this->max_discount,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'expires_at' => $this->expires_at->toIso8601String(),
            'is_active' => $this->is_active,
            'is_saved' => $saved,
        ];
    }
}
