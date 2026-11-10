<?php

namespace App\Domain\Inventory;

class InventoryPolicy extends Record
{
    protected $table = 'inventory_policies';

    protected $attributes = ['active' => true];

    protected function casts(): array
    {
        return ['requires_expiry' => 'boolean', 'active' => 'boolean'];
    }
}
