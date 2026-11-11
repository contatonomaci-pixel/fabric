<?php

namespace App\Domain\Inventory;

class InventoryBalance extends Record
{
    protected $table = 'inventory_balances';

    protected function casts(): array
    {
        return ['units' => 'decimal:6', 'grams' => 'decimal:6', 'reserved_units' => 'decimal:6', 'reserved_grams' => 'decimal:6'];
    }
}
