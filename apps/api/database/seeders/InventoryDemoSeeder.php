<?php

namespace Database\Seeders;

use App\Domain\Catalog\Warehouse;
use App\Domain\Inventory\InventoryDirectory;
use App\Domain\Inventory\Location;
use App\Domain\Purchasing\Supplier;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;

class InventoryDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Demonstração permitida somente em local/testing.');
        }
        $company = Company::where('is_demo', true)->where('trade_name', 'Fábrica Demonstração — FICTÍCIA')->firstOrFail();
        $actor = User::where('email', 'demo@example.test')->where('is_demo', true)->firstOrFail();
        $directory = app(InventoryDirectory::class);
        if (! Supplier::where('company_id', $company->id)->where('name', 'Fornecedor Demonstração — FICTÍCIO')->exists()) {
            $directory->save($actor, $company, 'suppliers', ['name' => 'Fornecedor Demonstração — FICTÍCIO', 'reason' => 'Demonstração FICTÍCIA, sem documento ou contato real.']);
        }
        $warehouse = Warehouse::where('company_id', $company->id)->where('name', 'Depósito Demonstração — FICTÍCIO')->firstOrFail();
        if (! Location::where('company_id', $company->id)->where('warehouse_id', $warehouse->id)->where('code', 'DEMO')->exists()) {
            $directory->save($actor, $company, 'locations', ['name' => 'Recebimento Demonstração — FICTÍCIO', 'code' => 'DEMO', 'warehouse_id' => $warehouse->public_id, 'reason' => 'Local de demonstração FICTÍCIO.']);
        }
        // Sem política, validade, quantidade, custo ou aprovação técnica inventados.
    }
}
