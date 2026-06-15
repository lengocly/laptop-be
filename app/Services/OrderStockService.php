<?php

namespace App\Services;

/** @deprecated Dùng InventoryService */
class OrderStockService
{
    public function __construct(private InventoryService $inventoryService) {}

    public function releaseForOrder(\App\Models\Order $order): void
    {
        $this->inventoryService->releaseForOrder($order);
    }
}
