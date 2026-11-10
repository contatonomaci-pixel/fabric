<?php

namespace App\Domain\Inventory;

class InventoryWeighing extends Immutable
{
    protected $table = 'inventory_weighings';

    protected function casts(): array
    {
        return ['gross_g' => 'decimal:6', 'tare_g' => 'decimal:6', 'net_g' => 'decimal:6'];
    }
}
