<?php

namespace App\Domain\Inventory;

class InventoryAllocation extends Immutable
{
    protected $table = 'inventory_allocations';

    protected function casts(): array
    {
        return ['units' => 'decimal:6', 'grams' => 'decimal:6'];
    }
}
