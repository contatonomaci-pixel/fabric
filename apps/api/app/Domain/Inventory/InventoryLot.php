<?php

namespace App\Domain\Inventory;

class InventoryLot extends Record
{
    protected $table = 'inventory_lots';

    protected function casts(): array
    {
        return ['manufactured_on' => 'date:Y-m-d', 'expires_on' => 'date:Y-m-d'];
    }
}
