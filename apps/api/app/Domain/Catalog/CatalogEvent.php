<?php

namespace App\Domain\Catalog;

class CatalogEvent extends ImmutableRecord
{
    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array'];
    }
}
