<?php

namespace App\Domain\Catalog;

class ProductVariant extends ScopedRecord
{
    protected function casts(): array
    {
        return [...parent::casts(), 'nominal_unit_weight_g' => 'decimal:6', 'attributes' => 'array'];
    }
}
