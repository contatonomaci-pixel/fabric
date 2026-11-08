<?php

namespace App\Domain\Catalog;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

abstract class ScopedRecord extends Model
{
    use HasUlids;

    protected $attributes = ['active' => true];

    protected $guarded = ['id', 'public_id', 'company_id'];

    protected $hidden = ['id', 'company_id'];

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }
}
