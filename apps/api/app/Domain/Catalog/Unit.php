<?php

namespace App\Domain\Catalog;

class Unit extends ScopedRecord
{
    protected $table = 'units_of_measure';

    protected function casts(): array
    {
        return [...parent::casts(), 'ratio_to_base' => 'decimal:6'];
    }
}
