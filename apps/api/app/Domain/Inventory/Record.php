<?php

namespace App\Domain\Inventory;

use App\Domain\Catalog\ScopedRecord;

abstract class Record extends ScopedRecord
{
    protected $attributes = [];
}
