<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Demonstração permitida somente em local/testing.');
        }
        $password = config('demo.password');
        if (! is_string($password) || strlen($password) < 12) {
            throw new \RuntimeException('Defina DEMO_PASSWORD local com pelo menos 12 caracteres.');
        }
        DB::transaction(function () use ($password) {
            $this->call(RoleSeeder::class);
            $company = Company::firstOrCreate(['trade_name' => 'Fábrica Demonstração — FICTÍCIA'], ['legal_name' => 'Empresa de testes fictícia', 'timezone' => 'America/Bahia', 'is_demo' => true]);
            $user = User::firstOrCreate(['email' => 'demo@example.test'], ['name' => 'Usuário demonstração', 'password' => $password]);
            if ($user->wasRecentlyCreated) {
                $user->forceFill(['is_demo' => true])->save();
            }
            $role = DB::table('roles')->where('code', 'owner')->value('id');
            $user->companies()->syncWithoutDetaching([$company->id => ['role_id' => $role]]);
            $this->call(CatalogDemoSeeder::class);
        });
    }
}
