<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, HasUlids, Notifiable;

    protected $attributes = ['active' => true, 'is_demo' => false];

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['id', 'password', 'remember_token'];

    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    protected function casts(): array
    {
        return ['active' => 'boolean', 'is_demo' => 'boolean', 'email_verified_at' => 'datetime', 'password' => 'hashed'];
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_users')->withPivot('role_id');
    }

    public function hasCompanyPermission(Company $company, string $permission, bool $allowInactive = false): bool
    {
        return $this->active && ($company->active || $allowInactive) && $this->companies()->where('companies.id', $company->id)
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('role_permissions')->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
                ->whereColumn('role_permissions.role_id', 'company_users.role_id')->where('permissions.code', $permission))->exists();
    }
}
