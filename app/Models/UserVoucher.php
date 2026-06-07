<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Voucher user đã lưu — dùng khi checkout
class UserVoucher extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'voucher_id',
        'saved_at',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'saved_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }
}
