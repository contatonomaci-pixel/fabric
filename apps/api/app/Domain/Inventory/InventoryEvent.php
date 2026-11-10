<?php

namespace App\Domain\Inventory;

class InventoryEvent extends Immutable
{
    protected $table = 'inventory_events';

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array'];
    }
}
