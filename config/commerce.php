<?php

return [
    'free_shipping_threshold' => (int) env('COMMERCE_FREE_SHIPPING_THRESHOLD', 10_000_000),
    'stripe_order_expire_minutes' => (int) env('COMMERCE_STRIPE_ORDER_EXPIRE_MINUTES', 30),
    'webhook_lease_seconds' => (int) env('COMMERCE_WEBHOOK_LEASE_SECONDS', 120),
    'refund_reconcile_delay_minutes' => (int) env('COMMERCE_REFUND_RECONCILE_DELAY_MINUTES', 5),
    'refund_pending_max_checks' => (int) env('COMMERCE_REFUND_PENDING_MAX_CHECKS', 24),
];
