<?php

namespace App\Domain\Purchasing;

use App\Domain\Inventory\Record;

class PurchaseOrder extends Record
{
    protected $table = 'purchase_orders';

    protected function casts(): array
    {
        return ['expected_on' => 'date:Y-m-d'];
    }
}
