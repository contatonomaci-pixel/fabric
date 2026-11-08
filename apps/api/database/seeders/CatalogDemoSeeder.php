<?php

namespace Database\Seeders;

use App\Domain\Catalog\CatalogDefinition;
use App\Domain\Catalog\CatalogService;
use App\Domain\Catalog\Unit;
use App\Domain\Catalog\UnitDefaults;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CatalogDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Demonstração permitida somente em local/testing.');
        }
        DB::transaction(function () {
            $company = Company::where('trade_name', 'Fábrica Demonstração — FICTÍCIA')->where('is_demo', true)->lockForUpdate()->firstOrFail();
            $actor = User::where('email', 'demo@example.test')->where('is_demo', true)->firstOrFail();
            UnitDefaults::ensure($company);
            $service = app(CatalogService::class);
            $ensure = function (string $resource, string $name, array $input) use ($service, $company, $actor) {
                $class = CatalogDefinition::MODELS[$resource];

                return $class::where('company_id', $company->id)->where('name', $name)->first()
                    ?? $service->save($actor, $company, $resource, ['name' => $name, ...$input]);
            };
            $factory = $ensure('factories', 'Unidade Demonstração — FICTÍCIA', ['timezone' => 'America/Bahia']);
            $ensure('warehouses', 'Depósito Demonstração — FICTÍCIO', ['factory_id' => $factory->public_id, 'kind' => 'general']);
            $units = Unit::where('company_id', $company->id)->get()->keyBy('code');
            foreach (['Coxinha' => [['Frango', '25'], ['Carne', '25']], 'Bolinho' => [['Carne', '50']], 'Pizza' => [['Frango', '400'], ['Carne', '400']]] as $productName => $flavors) {
                $family = $ensure('product-families', $productName, []);
                $product = $ensure('products', $productName, ['type' => 'finished', 'family_id' => $family->public_id]);
                foreach ($flavors as [$flavor, $grams]) {
                    $variant = $ensure('product-variants', $productName.' '.$flavor.' '.$grams.'g', ['product_id' => $product->public_id, 'flavor' => $flavor, 'nominal_unit_weight_g' => $grams, 'attributes' => ['Demonstração' => 'Fictícia; não usar como especificação técnica']]);
                    $ensure('commercial-skus', $variant->name.' UN', ['variant_id' => $variant->public_id, 'unit_id' => $units['UN']->public_id, 'packaging_qty' => '1', 'nominal_net_weight_g' => $grams, 'variable_weight' => false, 'price' => null]);
                    if ($productName === 'Coxinha' && $flavor === 'Frango') {
                        $ensure('commercial-skus', $variant->name.' PCT20', ['variant_id' => $variant->public_id, 'unit_id' => $units['PCT']->public_id, 'packaging_qty' => '20', 'nominal_net_weight_g' => '500', 'variable_weight' => false, 'price' => null]);
                        $ensure('commercial-skus', $variant->name.' KG', ['variant_id' => $variant->public_id, 'unit_id' => $units['KG']->public_id, 'packaging_qty' => null, 'nominal_net_weight_g' => null, 'variable_weight' => true, 'price' => null]);
                    }
                }
            }
        }, 5);
    }
}
