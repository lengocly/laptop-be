<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StripeWebhookEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'event_id',
        'type',
        'status',
        'error_message',
        'processing_owner',
        'processing_started_at',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'processing_started_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
