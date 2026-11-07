<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasUlids;

    protected $attributes = ['active' => true, 'is_demo' => false];

    protected $fillable = ['legal_name', 'trade_name', 'timezone', 'active', 'is_demo'];

    protected $hidden = ['id'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'is_demo' => 'boolean'];
    }

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
