<?php

namespace App\Policies;

use App\Domain\Catalog\CatalogDefinition;
use App\Models\Company;
use App\Models\User;

class CatalogPolicy
{
    public function view(User $user, Company $company, string $resource): bool
    {
        return $user->hasCompanyPermission($company, CatalogDefinition::permission($resource, false));
    }

    public function manage(User $user, Company $company, string $resource): bool
    {
        return $user->hasCompanyPermission($company, CatalogDefinition::permission($resource, true));
    }
}
