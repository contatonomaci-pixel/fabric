<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $codes = ['companies.view', 'companies.create', 'companies.manage', 'catalog.view', 'catalog.manage', 'catalog.audit'];
            $codes = [...$codes, 'purchasing.view', 'purchasing.manage', 'purchasing.receive', 'purchasing.costs', 'inventory.view', 'inventory.configure', 'inventory.manage', 'inventory.reserve', 'inventory.issue', 'inventory.count', 'inventory.reverse', 'inventory.release', 'inventory.weigh', 'inventory.audit'];
            foreach ($codes as $permissionCode) {
                DB::table('permissions')->updateOrInsert(['code' => $permissionCode]);
            }
            foreach (['owner' => 'Proprietário', 'auditor' => 'Auditor', 'operator' => 'Operador', 'buyer' => 'Compras', 'warehouse' => 'Almoxarifado', 'quality' => 'Qualidade'] as $code => $name) {
                DB::table('roles')->updateOrInsert(['code' => $code], ['name' => $name]);
                $role = DB::table('roles')->where('code', $code)->first();
                $allowed = match ($code) {
                    'owner' => $codes,
                    'auditor' => ['companies.view', 'catalog.view', 'catalog.audit', 'purchasing.view', 'inventory.view', 'inventory.audit'],
                    'buyer' => ['companies.view', 'catalog.view', 'purchasing.view', 'purchasing.manage', 'purchasing.costs'],
                    'warehouse' => ['companies.view', 'catalog.view', 'purchasing.view', 'purchasing.receive', 'inventory.view', 'inventory.manage', 'inventory.reserve', 'inventory.issue', 'inventory.count', 'inventory.weigh'],
                    'quality' => ['companies.view', 'catalog.view', 'inventory.view', 'inventory.release', 'inventory.audit'],
                    default => [],
                };
                foreach ($allowed as $permissionCode) {
                    DB::table('role_permissions')->updateOrInsert(['role_id' => $role->id, 'permission_id' => DB::table('permissions')->where('code', $permissionCode)->value('id')]);
                }
            }
        });
    }
}
