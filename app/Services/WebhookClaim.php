<?php

namespace App\Services;

use App\Models\StripeWebhookEvent;

class WebhookClaim
{
    public function __construct(
        public StripeWebhookEvent $event,
        public string $owner,
    ) {}
}
