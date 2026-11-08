<?php

namespace App\Domain\Catalog;

class CommercialSku extends ScopedRecord
{
    protected $attributes = ['active' => true, 'variable_weight' => false];

    protected function casts(): array
    {
        return [...parent::casts(), 'packaging_qty' => 'decimal:6', 'nominal_net_weight_g' => 'decimal:6', 'price' => 'decimal:4', 'variable_weight' => 'boolean'];
    }
}
