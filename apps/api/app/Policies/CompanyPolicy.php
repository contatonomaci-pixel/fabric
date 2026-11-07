<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class CompanyPolicy
{
    public function create(User $user): bool
    {
        return $user->active && $user->companies()->where('companies.active', true)
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('role_permissions')
                ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
                ->whereColumn('role_permissions.role_id', 'company_users.role_id')->where('permissions.code', 'companies.create'))->exists();
    }

    public function update(User $user, Company $company): bool
    {
        return $user->hasCompanyPermission($company, 'companies.manage', true);
    }

    public function view(User $user, Company $company): bool
    {
        return $user->hasCompanyPermission($company, 'companies.view');
    }
}
