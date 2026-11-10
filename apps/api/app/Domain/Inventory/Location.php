<?php

namespace App\Domain\Inventory;

class Location extends Record
{
    protected $table = 'locations';

    protected $attributes = ['active' => true];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
