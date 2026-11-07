<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            DB::table('permissions')->updateOrInsert(['code' => 'companies.view']);
            $permission = DB::table('permissions')->where('code', 'companies.view')->first();
            foreach (['owner' => 'Proprietário', 'auditor' => 'Auditor', 'operator' => 'Operador'] as $code => $name) {
                DB::table('roles')->updateOrInsert(['code' => $code], ['name' => $name]);
                $role = DB::table('roles')->where('code', $code)->first();
                if ($code !== 'operator') {
                    DB::table('role_permissions')->updateOrInsert(['role_id' => $role->id, 'permission_id' => $permission->id]);
                }
            }
        });
    }
}
