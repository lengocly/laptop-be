<?php

namespace App\Enums;

enum InventoryTransactionType: string
{
    case Reserve = 'reserve';
    case Release = 'release';
    case Sale = 'sale';
    case Restock = 'restock';
}
