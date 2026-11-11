<?php

namespace App\Domain\Purchasing;

use App\Domain\Inventory\Record;

class PurchaseOrderItem extends Record
{
    protected $table = 'purchase_order_items';

    protected function casts(): array
    {
        return ['quantity' => 'decimal:6', 'received_quantity' => 'decimal:6', 'unit_cost' => 'decimal:4', 'total_cost' => 'decimal:4'];
    }
}
