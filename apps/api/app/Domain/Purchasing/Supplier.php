<?php

namespace App\Domain\Purchasing;

use App\Domain\Inventory\Record;

class Supplier extends Record
{
    protected $table = 'suppliers';

    protected $attributes = ['active' => true];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
