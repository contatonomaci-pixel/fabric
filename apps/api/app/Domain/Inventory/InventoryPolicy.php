<?php

namespace App\Domain\Inventory;

use App\Domain\Purchasing\PurchaseOrderItem;

class InventoryPolicy extends Record
{
    protected $table = 'inventory_policies';

    protected $attributes = ['active' => true];

    public function identityLocked(): bool
    {
        return InventoryLot::where('company_id', $this->company_id)->where('policy_id', $this->id)->exists()
            || PurchaseOrderItem::where('company_id', $this->company_id)->where('policy_id', $this->id)->exists();
    }

    protected function casts(): array
    {
        return ['requires_expiry' => 'boolean', 'active' => 'boolean'];
    }
}
