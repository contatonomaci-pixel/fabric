<?php

namespace App\Domain\Inventory;

class InventoryCount extends Immutable
{
    protected $table = 'inventory_counts';

    protected function casts(): array
    {
        return ['expected_units' => 'decimal:6', 'expected_grams' => 'decimal:6', 'actual_units' => 'decimal:6', 'actual_grams' => 'decimal:6'];
    }
}
