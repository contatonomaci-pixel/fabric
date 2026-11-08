<?php

namespace App\Domain\Identity;

use App\Domain\Catalog\CatalogService;
use App\Domain\Catalog\UnitDefaults;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ManageCompany
{
    public function save(User $actor, array $data, ?Company $company = null): Company
    {
        Gate::forUser($actor)->authorize($company ? 'update' : 'create', $company ?? Company::class);

        return DB::transaction(function () use ($actor, $data, $company) {
            if ($company) {
                $company = Company::whereKey($company->id)->lockForUpdate()->firstOrFail();
                Gate::forUser($actor)->authorize('update', $company);
            }
            $before = $company?->only(['public_id', 'legal_name', 'trade_name', 'timezone', 'active']);
            $company ??= new Company;
            $creating = ! $company->exists;
            $company->fill($data)->save();
            if ($creating) {
                $actor->companies()->attach($company->id, ['role_id' => DB::table('roles')->where('code', 'owner')->value('id')]);
                UnitDefaults::ensure($company);
            }
            CatalogService::audit($actor, $company, 'companies', $company->public_id, $creating ? 'created' : 'updated', $before, $company->only(['public_id', 'legal_name', 'trade_name', 'timezone', 'active']));

            return $company;
        }, 5);
    }
}
