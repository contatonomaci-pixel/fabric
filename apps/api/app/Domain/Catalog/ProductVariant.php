<?php

namespace App\Domain\Catalog;

use App\Domain\Inventory\InventoryLot;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends ScopedRecord
{
    public function skus(): HasMany
    {
        return $this->hasMany(CommercialSku::class, 'variant_id');
    }

    public function inventoryLots(): HasMany
    {
        return $this->hasMany(InventoryLot::class, 'variant_id');
    }

    protected function casts(): array
    {
        return [...parent::casts(), 'nominal_unit_weight_g' => 'decimal:6', 'attributes' => 'array'];
    }
}
