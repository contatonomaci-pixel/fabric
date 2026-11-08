<?php

namespace Tests\Feature;

use App\Domain\Catalog\BarcodeRenderer;
use App\Domain\Catalog\CatalogEvent;
use App\Domain\Catalog\CatalogService;
use App\Domain\Catalog\CodeReservation;
use App\Domain\Catalog\CommercialSku;
use App\Domain\Catalog\Product;
use App\Domain\Catalog\ProductBarcode;
use App\Domain\Catalog\ProductFamily;
use App\Domain\Catalog\ProductVariant;
use App\Domain\Catalog\Unit;
use App\Domain\Catalog\UnitDefaults;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Company $company;

    private string $base;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.password' => 'Ficticia-Segura123!']);
        $this->seed(DemoSeeder::class);
        $this->owner = User::where('email', 'demo@example.test')->firstOrFail();
        $this->company = Company::firstOrFail();
        $this->base = '/api/v1/companies/'.$this->company->public_id;
        $this->actingAs($this->owner);
    }

    private function skuInput(array $extra = []): array
    {
        return ['name' => 'SKU de teste FICTÍCIO', 'variant_id' => ProductVariant::first()->public_id, 'unit_id' => Unit::where('code', 'UN')->first()->public_id, 'variable_weight' => false, 'price' => '12.3456', ...$extra];
    }

    private function auditor(): User
    {
        $user = User::factory()->create();
        $user->companies()->attach($this->company, ['role_id' => DB::table('roles')->where('code', 'auditor')->value('id')]);

        return $user;
    }

    public function test_fictitious_catalog_seed_is_idempotent_and_has_distinct_presentations(): void
    {
        $this->seed(DemoSeeder::class);
        $this->assertDatabaseCount('products', 3);
        $this->assertDatabaseCount('product_variants', 5);
        $this->assertDatabaseCount('commercial_skus', 7);
        $this->assertDatabaseCount('product_barcodes', 7);
        $this->assertDatabaseCount('code_reservations', 14);
        $this->assertSame(7, CommercialSku::distinct()->count('sku'));
        $this->assertSame(0, CommercialSku::whereNotNull('price')->count());
        $this->assertSame('1000.000000', Unit::where('code', 'KG')->first()->ratio_to_base);
        $this->assertNull(Unit::where('code', 'PCT')->first()->ratio_to_base);
        if (DB::getDriverName() === 'mysql') {
            $tables = DB::select("SELECT ENGINE, TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('factories','warehouses','units_of_measure','product_families','products','product_variants','commercial_skus','product_barcodes','code_reservations','catalog_events')");
            $this->assertCount(10, $tables);
            foreach ($tables as $table) {
                $this->assertSame('InnoDB', $table->ENGINE);
                $this->assertStringStartsWith('utf8mb4', $table->TABLE_COLLATION);
            }
            $columns = DB::select("SELECT COLUMN_NAME, DATA_TYPE, NUMERIC_PRECISION, NUMERIC_SCALE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'commercial_skus' AND COLUMN_NAME IN ('price','packaging_qty','nominal_net_weight_g')");
            $this->assertCount(3, $columns);
            foreach ($columns as $column) {
                $this->assertSame('decimal', $column->DATA_TYPE);
                $this->assertSame(18, (int) $column->NUMERIC_PRECISION);
                $this->assertSame($column->COLUMN_NAME === 'price' ? 4 : 6, (int) $column->NUMERIC_SCALE);
            }
        }
    }

    public function test_company_creation_sets_owner_scope_and_defaults_without_leaking_other_tenants(): void
    {
        $id = $this->postJson('/api/v1/companies', ['trade_name' => 'Empresa nova FICTÍCIA', 'legal_name' => 'Teste FICTÍCIO', 'timezone' => 'America/Bahia'])->assertCreated()->assertJsonPath('data.active', true)->assertJsonPath('data.is_demo', false)->json('data.public_id');
        $this->assertDatabaseCount('companies', 2);
        $this->getJson('/api/v1/companies/'.$id.'/units')->assertOk()->assertJsonPath('meta.total', 5);
        $this->actingAs($this->auditor())->postJson('/api/v1/companies', ['trade_name' => 'Não autorizado'])->assertForbidden();
        $this->patchJson($this->base, ['trade_name' => 'Não autorizado'])->assertForbidden();
        $this->actingAs($this->owner)->patchJson('/api/v1/companies/'.$id, ['active' => false])->assertOk();
        $this->getJson('/api/v1/companies/'.$id.'/products')->assertForbidden();
        $this->getJson('/api/v1/companies?include_inactive=1')->assertJsonPath('meta.total', 2);
        $this->patchJson('/api/v1/companies/'.$id, ['active' => true])->assertOk();
    }

    public function test_auditor_can_read_but_never_mutate_catalog_structure_or_reserve_codes(): void
    {
        $sku = CommercialSku::first();
        $this->actingAs($this->auditor());
        foreach (['factories', 'warehouses', 'product-families', 'products', 'product-variants', 'units', 'commercial-skus'] as $resource) {
            $this->getJson($this->base.'/'.$resource)->assertOk();
            $this->postJson($this->base.'/'.$resource, [])->assertForbidden();
        }
        $this->patchJson($this->base.'/commercial-skus/'.$sku->public_id, ['price' => '0'])->assertForbidden();
        $this->postJson($this->base.'/barcodes/reserve', ['sku_id' => $sku->public_id, 'kind' => 'internal_code128'])->assertForbidden();
        $this->getJson($this->base.'/catalog-history')->assertOk();
    }

    public function test_resource_ids_and_foreign_keys_cannot_cross_companies(): void
    {
        $other = Company::create(['trade_name' => 'Outra FICTÍCIA', 'legal_name' => 'Outra FICTÍCIA']);
        UnitDefaults::ensure($other);
        $foreign = new ProductFamily;
        $foreign->forceFill(['company_id' => $other->id, 'name' => 'Segredo da outra empresa FICTÍCIA'])->save();
        $this->getJson('/api/v1/companies/'.$other->public_id.'/product-families')->assertForbidden();
        $this->getJson($this->base.'/product-families/'.$foreign->public_id)->assertNotFound();
        $this->patchJson($this->base.'/product-families/'.$foreign->public_id, ['name' => 'Ataque'])->assertNotFound();
        $this->postJson($this->base.'/products', ['name' => 'Teste', 'type' => 'finished', 'family_id' => $foreign->public_id])->assertUnprocessable()->assertJsonValidationErrors('family_id');
        $this->postJson($this->base.'/commercial-skus', $this->skuInput(['unit_id' => Unit::where('company_id', $other->id)->first()->public_id]))->assertUnprocessable();
        $this->postJson($this->base.'/products', ['name' => 'Teste', 'type' => 'finished', 'family_id' => ProductFamily::where('company_id', $this->company->id)->first()->public_id, 'company_id' => $other->public_id])->assertUnprocessable();
    }

    public function test_composite_foreign_key_rejects_cross_tenant_links_in_database(): void
    {
        $other = Company::create(['trade_name' => 'Outra FICTÍCIA', 'legal_name' => 'Outra FICTÍCIA']);
        UnitDefaults::ensure($other);
        $this->expectException(QueryException::class);
        DB::table('commercial_skus')->insert(['public_id' => (string) Str::ulid(), 'company_id' => $this->company->id, 'variant_id' => ProductVariant::first()->id, 'unit_id' => Unit::where('company_id', $other->id)->first()->id, 'name' => 'Violação FICTÍCIA', 'sku' => 'CROSS-FK-TEST']);
    }

    public function test_duplicate_inactive_sku_is_never_reused_and_history_survives(): void
    {
        $id = $this->postJson($this->base.'/commercial-skus', $this->skuInput(['sku' => '  ficticio-001  ']))->assertCreated()->assertJsonPath('data.sku', 'FICTICIO-001')->json('data.public_id');
        $this->patchJson($this->base.'/commercial-skus/'.$id, ['active' => false])->assertOk()->assertJsonPath('data.effective_active', false);
        $this->postJson($this->base.'/commercial-skus', $this->skuInput(['sku' => 'FICTICIO-001']))->assertUnprocessable()->assertJsonValidationErrors('sku');
        $this->assertSame(1, CodeReservation::where('namespace', 'sku')->where('code', 'FICTICIO-001')->count());
        $this->getJson($this->base.'/catalog-history?resource_id='.$id)->assertOk()->assertJsonPath('meta.total', 2);
        $this->deleteJson($this->base.'/commercial-skus/'.$id)->assertStatus(405);
    }

    public function test_price_is_exact_decimal_audited_and_presentation_is_immutable(): void
    {
        $id = $this->postJson($this->base.'/commercial-skus', $this->skuInput())->assertCreated()->assertJsonPath('data.price', '12.3456')->json('data.public_id');
        $this->patchJson($this->base.'/commercial-skus/'.$id, ['price' => '99999999999999.9999'])->assertOk()->assertJsonPath('data.price', '99999999999999.9999');
        $event = CatalogEvent::where('resource_public_id', $id)->orderByDesc('id')->first();
        $this->assertSame('12.3456', $event->before['price']);
        $this->assertSame('99999999999999.9999', $event->after['price']);
        $this->patchJson($this->base.'/commercial-skus/'.$id, ['sku' => 'ALTERADO'])->assertUnprocessable();
        $this->patchJson($this->base.'/commercial-skus/'.$id, ['packaging_qty' => '2'])->assertUnprocessable();
        $this->patchJson($this->base.'/commercial-skus/'.$id, ['price' => '-1.00'])->assertUnprocessable();
        $this->patchJson($this->base.'/commercial-skus/'.$id, ['price' => '1.23456'])->assertUnprocessable();
        $this->patchJson($this->base.'/commercial-skus/'.$id, ['price' => 1.2])->assertUnprocessable();
    }

    public function test_unit_and_packaging_rules_do_not_use_nominal_weight_as_real_stock(): void
    {
        $kg = Unit::where('code', 'KG')->first();
        $pct = Unit::where('code', 'PCT')->first();
        $this->postJson($this->base.'/commercial-skus', $this->skuInput(['unit_id' => $kg->public_id, 'variable_weight' => false]))->assertUnprocessable();
        $this->postJson($this->base.'/commercial-skus', $this->skuInput(['unit_id' => $kg->public_id, 'variable_weight' => true, 'nominal_net_weight_g' => '25']))->assertUnprocessable();
        $this->postJson($this->base.'/commercial-skus', $this->skuInput(['unit_id' => $pct->public_id]))->assertUnprocessable();
        $this->postJson($this->base.'/commercial-skus', $this->skuInput(['unit_id' => $pct->public_id, 'packaging_qty' => '20']))->assertCreated();
        $this->postJson($this->base.'/units', ['name' => 'Caixa FICTÍCIA', 'code' => 'CX2', 'dimension' => 'package', 'ratio_to_base' => '20'])->assertUnprocessable();
        $this->patchJson($this->base.'/units/'.$kg->public_id, ['ratio_to_base' => '1'])->assertUnprocessable();
        $this->postJson($this->base.'/units', ['name' => 'Zero FICTÍCIO', 'code' => 'Z', 'dimension' => 'count', 'ratio_to_base' => '0'])->assertUnprocessable();
        $this->postJson($this->base.'/units', ['name' => 'Inválido', 'code' => 'Z', 'dimension' => 'mass', 'ratio_to_base' => 'abc'])->assertUnprocessable();
    }

    public function test_variant_identity_cannot_change_after_skus_and_family_cycles_are_rejected(): void
    {
        $variant = ProductVariant::first();
        $this->patchJson($this->base.'/product-variants/'.$variant->public_id, ['flavor' => 'Outro sabor'])->assertUnprocessable();
        $parent = ProductFamily::first();
        $childId = $this->postJson($this->base.'/product-families', ['name' => 'Filha FICTÍCIA', 'parent_id' => $parent->public_id])->assertCreated()->json('data.public_id');
        $this->patchJson($this->base.'/product-families/'.$parent->public_id, ['parent_id' => $childId])->assertUnprocessable();
        $this->patchJson($this->base.'/products/'.Product::first()->public_id, ['active' => false])->assertOk();
        $this->postJson($this->base.'/commercial-skus', $this->skuInput())->assertUnprocessable();
        $this->assertDatabaseCount('code_reservations', 14);
    }

    public function test_barcode_reservation_is_idempotent_and_rejects_rcn_and_gtin(): void
    {
        $id = $this->postJson($this->base.'/commercial-skus', $this->skuInput(['generate_internal_barcode' => false]))->assertCreated()->json('data.public_id');
        $payload = ['sku_id' => $id, 'kind' => 'internal_code128'];
        $code = $this->postJson($this->base.'/barcodes/reserve', $payload)->assertOk()->json('data.code');
        $this->postJson($this->base.'/barcodes/reserve', $payload)->assertOk()->assertJsonPath('data.code', $code);
        $this->assertDatabaseCount('product_barcodes', 8);
        foreach (['RCN', 'gtin_official', 'ean13'] as $kind) {
            $this->postJson($this->base.'/barcodes/reserve', [...$payload, 'kind' => $kind])->assertUnprocessable();
        }
        $this->postJson($this->base.'/barcodes/validate', ['code' => $code, 'kind' => 'internal_code128'])->assertOk()->assertJsonPath('data.recognized', true)->assertJsonPath('data.sku.public_id', $id)->assertJsonPath('data.gtin_official', false)->assertJsonPath('data.retail_approved', false);
    }

    public function test_barcode_exports_are_real_files_with_white_quiet_zones_and_authorization(): void
    {
        $sku = CommercialSku::first();
        $this->get($this->base.'/commercial-skus/'.$sku->public_id.'/barcode.svg')->assertOk()->assertHeader('Content-Type', 'image/svg+xml')->assertSee('translate(20 10)', false);
        $png = $this->get($this->base.'/commercial-skus/'.$sku->public_id.'/barcode.png')->assertOk()->getContent();
        $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $png);
        $image = imagecreatefromstring($png);
        foreach ([0, 19, imagesx($image) - 20, imagesx($image) - 1] as $x) {
            $this->assertSame(0xFFFFFF, imagecolorat($image, $x, 40));
        }
        $this->assertSame(0, imagecolorat($image, 20, 40));
        if (getenv('BARCODE_DECODER')) {
            $file = tempnam(sys_get_temp_dir(), 'fc-barcode-');
            try {
                file_put_contents($file, $png);
                $decoder = new Process([getenv('BARCODE_DECODER'), '--quiet', '--raw', $file]);
                $decoder->mustRun();
                $this->assertSame(ProductBarcode::where('sku_id', $sku->id)->first()->code, trim($decoder->getOutput()));
            } finally {
                unlink($file);
            }
        }
        $this->get($this->base.'/commercial-skus/'.$sku->public_id.'/barcode.pdf')->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertSee('%PDF-', false);
        $sku->forceFill(['name' => str_repeat('W', 150), 'sku' => str_repeat('W', 40)]);
        $longPdf = app(BarcodeRenderer::class)->pdf(ProductBarcode::where('sku_id', $sku->id)->first(), $sku);
        $this->assertSame(1, preg_match_all('/\/Type\s*\/Page\b/', $longPdf), 'A etiqueta de teste deve permanecer em uma página, inclusive com textos longos.');
        $this->actingAs(User::factory()->create())->get($this->base.'/commercial-skus/'.$sku->public_id.'/barcode.png')->assertForbidden();
    }

    public function test_filters_pagination_and_payloads_use_only_public_ids(): void
    {
        $family = ProductFamily::where('name', 'Pizza')->first();
        $data = $this->getJson($this->base.'/commercial-skus?family_id='.$family->public_id.'&type=finished&per_page=1')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 2)->json('data.0');
        foreach (['id', 'company_id'] as $private) {
            $this->assertArrayNotHasKey($private, $data);
        }
        $this->assertTrue(Str::isUlid($data['variant_id']));
        $this->assertTrue(Str::isUlid($data['unit_id']));
        $this->getJson($this->base.'/commercial-skus?q=PCT20')->assertJsonPath('meta.total', 1);
        $barcode = ProductBarcode::first();
        $this->getJson($this->base.'/commercial-skus?q='.$barcode->code)->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.public_id', CommercialSku::findOrFail($barcode->sku_id)->public_id);
        $variant = ProductVariant::where('name', 'Coxinha Frango 25g')->first();
        $this->getJson($this->base.'/commercial-skus?variant_id='.$variant->public_id)->assertJsonPath('meta.total', 3);
        $this->getJson($this->base.'/commercial-skus?per_page=1000')->assertUnprocessable();
    }

    public function test_read_budget_does_not_consume_mutation_budget_and_writes_remain_limited(): void
    {
        config(['workspace.read_per_minute' => 2, 'workspace.write_per_minute' => 1]);
        $this->getJson($this->base.'/products')->assertOk();
        $this->getJson($this->base.'/products')->assertOk();
        $this->getJson($this->base.'/products')->assertStatus(429);
        $this->postJson($this->base.'/product-families', ['name' => 'Limite FICTÍCIO'])->assertCreated();
        $this->postJson($this->base.'/product-families', ['name' => 'Segunda FICTÍCIA'])->assertStatus(429);
    }

    public function test_write_rechecks_company_state_inside_transaction(): void
    {
        Company::whereKey($this->company->id)->update(['active' => false]);
        $this->expectException(AuthorizationException::class);
        app(CatalogService::class)->save($this->owner, $this->company, 'product-families', ['name' => 'Não gravar após desativação']);
    }

    public function test_twenty_family_levels_keep_products_variants_and_skus_available(): void
    {
        $service = app(CatalogService::class);
        $parent = null;
        $root = null;
        for ($level = 1; $level <= 20; $level++) {
            $parent = $service->save($this->owner, $this->company, 'product-families', ['name' => 'Nível FICTÍCIO '.$level, 'parent_id' => $parent?->public_id]);
            $root ??= $parent;
        }
        $this->postJson($this->base.'/product-families', ['name' => 'Nível excedido', 'parent_id' => $parent->public_id])->assertUnprocessable();
        $product = $this->postJson($this->base.'/products', ['name' => 'Produto profundo FICTÍCIO', 'family_id' => $parent->public_id, 'type' => 'finished'])->assertCreated()->assertJsonPath('data.effective_active', true)->json('data.public_id');
        $variant = $this->postJson($this->base.'/product-variants', ['name' => 'Variante profunda FICTÍCIA', 'product_id' => $product])->assertCreated()->assertJsonPath('data.effective_active', true)->json('data.public_id');
        $sku = $this->postJson($this->base.'/commercial-skus', $this->skuInput(['variant_id' => $variant]))->assertCreated()->assertJsonPath('data.effective_active', true)->json('data.public_id');
        $this->getJson($this->base.'/commercial-skus?q=SKU%20de%20teste')->assertOk()->assertJsonPath('data.0.effective_active', true);
        $this->patchJson($this->base.'/product-families/'.$root->public_id, ['active' => false])->assertOk();
        $this->getJson($this->base.'/commercial-skus/'.$sku)->assertOk()->assertJsonPath('data.effective_active', false);
        $this->patchJson($this->base.'/product-families/'.$root->public_id, ['active' => true])->assertOk();
        $this->getJson($this->base.'/commercial-skus/'.$sku)->assertOk()->assertJsonPath('data.effective_active', true);
        // Corrupção simulada fora da API: a leitura também deve encerrar em ciclos.
        ProductFamily::whereKey($root->id)->update(['parent_id' => $parent->id]);
        $this->assertFalse($service->available('product-families', $root->fresh()));
    }

    public function test_reparenting_counts_descendants_before_accepting_twenty_levels(): void
    {
        $service = app(CatalogService::class);
        $parents = [];
        for ($level = 0; $level < 18; $level++) {
            $parents[] = $service->save($this->owner, $this->company, 'product-families', ['name' => 'Tronco FICTÍCIO '.$level, 'parent_id' => $level ? $parents[$level - 1]->public_id : null]);
        }
        $branch = $service->save($this->owner, $this->company, 'product-families', ['name' => 'Ramo FICTÍCIO']);
        $child = $service->save($this->owner, $this->company, 'product-families', ['name' => 'Filho FICTÍCIO', 'parent_id' => $branch->public_id]);
        $leaf = $service->save($this->owner, $this->company, 'product-families', ['name' => 'Folha FICTÍCIA', 'parent_id' => $child->public_id]);
        $this->patchJson($this->base.'/product-families/'.$branch->public_id, ['parent_id' => $parents[17]->public_id])->assertUnprocessable()->assertJsonValidationErrors('parent_id');
        $this->assertNull($branch->fresh()->parent_id);
        $this->patchJson($this->base.'/product-families/'.$branch->public_id, ['parent_id' => $parents[16]->public_id])->assertOk();
        $this->getJson($this->base.'/product-families/'.$leaf->public_id)->assertOk()->assertJsonPath('data.effective_active', true);
    }

    public function test_variant_reports_identity_lock_only_after_first_sku_including_inactive(): void
    {
        $variant = $this->postJson($this->base.'/product-variants', ['name' => 'Sem SKU FICTÍCIA', 'product_id' => Product::first()->public_id, 'flavor' => 'Frango', 'nominal_unit_weight_g' => '30'])->assertCreated()->assertJsonPath('data.identity_locked', false)->json('data.public_id');
        $this->patchJson($this->base.'/product-variants/'.$variant, ['flavor' => 'Carne', 'nominal_unit_weight_g' => '35', 'attributes' => ['Formato' => 'Fictício']])->assertOk()->assertJsonPath('data.flavor', 'Carne')->assertJsonPath('data.identity_locked', false);
        $sku = $this->postJson($this->base.'/commercial-skus', $this->skuInput(['variant_id' => $variant]))->assertCreated()->json('data.public_id');
        $this->patchJson($this->base.'/commercial-skus/'.$sku, ['active' => false])->assertOk();
        $this->getJson($this->base.'/product-variants/'.$variant)->assertOk()->assertJsonPath('data.identity_locked', true);
        $this->getJson($this->base.'/product-variants?q=Sem%20SKU')->assertOk()->assertJsonPath('data.0.identity_locked', true);
        $this->patchJson($this->base.'/product-variants/'.$variant, ['flavor' => 'Outro'])->assertUnprocessable();
        $this->patchJson($this->base.'/product-variants/'.$variant, ['name' => 'Nome corrigido FICTÍCIO'])->assertOk()->assertJsonPath('data.identity_locked', true);
    }
}
