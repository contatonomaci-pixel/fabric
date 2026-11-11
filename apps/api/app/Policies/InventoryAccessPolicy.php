<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\User;

class InventoryAccessPolicy
{
    public function access(User $user, Company $company, string $permission): bool
    {
        return $user->hasCompanyPermission($company, $permission);
    }
}
