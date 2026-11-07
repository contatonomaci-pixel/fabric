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
            foreach ($codes as $permissionCode) {
                DB::table('permissions')->updateOrInsert(['code' => $permissionCode]);
            }
            foreach (['owner' => 'Proprietário', 'auditor' => 'Auditor', 'operator' => 'Operador'] as $code => $name) {
                DB::table('roles')->updateOrInsert(['code' => $code], ['name' => $name]);
                $role = DB::table('roles')->where('code', $code)->first();
                $allowed = match ($code) {
                    'owner' => $codes,
                    'auditor' => ['companies.view', 'catalog.view', 'catalog.audit'],
                    default => [],
                };
                foreach ($allowed as $permissionCode) {
                    DB::table('role_permissions')->updateOrInsert(['role_id' => $role->id, 'permission_id' => DB::table('permissions')->where('code', $permissionCode)->value('id')]);
                }
            }
        });
    }
}
