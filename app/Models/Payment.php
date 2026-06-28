<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class Payment extends Model
{
    protected $fillable = [
        'order_id',
        'provider',
        'amount',
        'currency',
        'status',
        'provider_reference',
        'idempotency_key',
        'meta',
    ];
    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}

