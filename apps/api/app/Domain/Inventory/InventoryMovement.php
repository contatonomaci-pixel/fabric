<?php

namespace App\Domain\Inventory;

class InventoryMovement extends Immutable
{
    protected $table = 'inventory_movements';

    protected function casts(): array
    {
        return ['units_delta' => 'decimal:6', 'grams_delta' => 'decimal:6'];
    }
}
