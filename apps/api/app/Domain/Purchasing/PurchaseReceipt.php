<?php

namespace App\Domain\Purchasing;

use App\Domain\Inventory\Immutable;

class PurchaseReceipt extends Immutable
{
    protected $table = 'purchase_receipts';

    protected function casts(): array
    {
        return ['received_on' => 'date:Y-m-d', 'quantity' => 'decimal:6', 'unit_cost' => 'decimal:4', 'total_cost' => 'decimal:4'];
    }
}
