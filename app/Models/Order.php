<?php

namespace App\Models;

use App\Services\OrderStateMachine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'order_status',
        'fulfillment_status',
        'full_name',
        'phone',
        'address',
        'to_district_id',
        'to_ward_code',
        'note',
        'admin_note',
        'items_subtotal',
        'subtotal',
        'shipping_fee',
        'voucher_id',
        'voucher_discount',
        'order_code',
        'payment_method',
        'payment_status',
        'stripe_payment_intent_id',
        'payment_attempt',
        'expires_at',
        'inventory_released_at',
        'voucher_released_at',
    ];

    protected $appends = ['status'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'inventory_released_at' => 'datetime',
            'voucher_released_at' => 'datetime',
        ];
    }

    /** Trạng thái legacy cho frontend. */
    public function getStatusAttribute(): string
    {
        return app(OrderStateMachine::class)->legacyStatus($this);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function inventoryTransactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }
}
