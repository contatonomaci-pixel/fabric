<?php

namespace Tests\Feature;

use App\Domain\Catalog\ProductVariant;
use App\Domain\Catalog\Unit;
use App\Domain\Catalog\Warehouse;
use App\Domain\Inventory\InventoryAllocation;
use App\Domain\Inventory\InventoryBalance;
use App\Domain\Inventory\InventoryEvent;
use App\Domain\Inventory\InventoryLot;
use App\Domain\Inventory\InventoryMovement;
use App\Domain\Inventory\InventoryOperation;
use App\Domain\Inventory\InventoryPolicy;
use App\Domain\Inventory\InventoryReservation;
use App\Domain\Inventory\InventoryWeighing;
use App\Domain\Inventory\Location;
use App\Domain\Purchasing\PurchaseOrder;
use App\Domain\Purchasing\PurchaseOrderItem;
use App\Domain\Purchasing\PurchaseReceipt;
use App\Domain\Purchasing\Supplier;
use App\Models\Company;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\DemoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $owner;

    private string $base;

    private ProductVariant $variant;

    private InventoryPolicy $policy;

    private Supplier $supplier;

    private Location $location;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-08 12:00:00', 'UTC'));
        config(['demo.password' => 'FICTICIA-Inventario123!']);
        $this->seed(DemoSeeder::class);
        $this->company = Company::firstOrFail();
        $this->owner = User::where('email', 'demo@example.test')->firstOrFail();
        $this->actingAs($this->owner);
        $this->base = '/api/v1/companies/'.$this->company->public_id;
        $this->variant = ProductVariant::firstOrFail();
        $this->supplier = $this->record(Supplier::class, ['name' => 'Fornecedor FICTÍCIO']);
        $this->policy = $this->record(InventoryPolicy::class, ['variant_id' => $this->variant->id, 'mode' => 'mass', 'requires_expiry' => true]);
        $this->location = $this->record(Location::class, ['warehouse_id' => Warehouse::first()->id, 'name' => 'Recebimento FICTÍCIO', 'code' => 'RCV']);
    }

    private function record(string $class, array $data): mixed
    {
        $row = new $class;
        $row->forceFill(['company_id' => $this->company->id, ...$data])->save();

        return $row;
    }

    private function command(string $kind, array $data, ?string $key = null)
    {
        return $this->postJson($this->base.'/inventory/actions/'.$kind, ['reason' => 'Operação de teste FICTÍCIA', 'idempotency_key' => $key ?? (string) Str::uuid(), ...$data]);
    }

    private function order(string $quantity = '10', string $unit = 'KG'): PurchaseOrderItem
    {
        $op = $this->command('order', ['supplier_id' => $this->supplier->public_id, 'items' => [['variant_id' => $this->variant->public_id, 'unit_id' => Unit::where('company_id', $this->company->id)->where('code', $unit)->first()->public_id, 'quantity' => $quantity, 'unit_cost' => '12.3456']]])->assertCreated()->json('data.public_id');

        return PurchaseOrderItem::where('order_id', PurchaseOrder::where('operation_id', InventoryOperation::where('public_id', $op)->value('id'))->value('id'))->firstOrFail();
    }

    private function receipt(PurchaseOrderItem $item, string $quantity = '2', string $status = 'released', string $expiry = '2026-12-01', array $extra = []): InventoryBalance
    {
        $payload = ['order_item_id' => $item->public_id, 'location_id' => $this->location->public_id, 'quantity' => $quantity, 'received_on' => '2026-11-08', 'expires_on' => $expiry, 'supplier_lot_code' => 'Lote FICTÍCIO '.Str::random(8), 'quality_status' => $status, 'gross_g' => bcadd(bcmul($quantity, '1000', 6), '10', 6), 'tare_g' => '10', ...$extra];
        $id = $this->command('receive', $payload)->assertCreated()->json('data.public_id');

        return InventoryBalance::findOrFail(PurchaseReceipt::where('operation_id', InventoryOperation::where('public_id', $id)->value('id'))->value('balance_id'));
    }

    private function reservationId(array $payload): string
    {
        return $this->command('reserve', ['variant_id' => $this->variant->public_id, 'warehouse_id' => Warehouse::first()->public_id, ...$payload])->assertCreated()->json('data.reservations.0');
    }

    public function test_issued_order_locks_policy_before_the_first_lot(): void
    {
        $this->order();
        $this->assertDatabaseCount('inventory_lots', 0);
        $path = $this->base.'/inventory/policies/'.$this->policy->public_id;
        $this->patchJson($path, ['mode' => 'count', 'reason' => 'Correção FICTÍCIA'])->assertUnprocessable();
        $this->patchJson($path, ['requires_expiry' => false, 'reason' => 'Correção FICTÍCIA'])->assertUnprocessable();
        $this->getJson($path)->assertOk()->assertJsonPath('data.identity_locked', true);
        $this->patchJson($path, ['mode' => 'mass', 'requires_expiry' => true, 'active' => false, 'reason' => 'Desativação FICTÍCIA'])->assertOk();
        $this->assertSame('mass', $this->policy->fresh()->mode);
    }

    public function test_receipt_rejects_legacy_order_with_incompatible_policy_dimension(): void
    {
        $item = $this->order('1');
        // Simula pedido histórico afetado por alteração permitida antes da correção.
        $this->policy->forceFill(['mode' => 'count'])->save();
        $this->command('receive', ['order_item_id' => $item->public_id, 'location_id' => $this->location->public_id, 'quantity' => '1', 'units' => '1000', 'received_on' => '2026-11-08', 'expires_on' => '2026-12-01', 'supplier_lot_code' => 'LEGADO-FICTICIO'])->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->assertDatabaseCount('inventory_lots', 0);
        $this->assertDatabaseCount('purchase_receipts', 0);
        $this->assertSame('0.000000', $item->fresh()->received_quantity);
    }

    public function test_standalone_dual_weighing_records_mass_without_discarding_units(): void
    {
        $this->policy->forceFill(['mode' => 'dual'])->save();
        $balance = $this->receipt($this->order(), '1', extra: ['units' => '7']);
        $this->command('weigh', ['balance_id' => $balance->public_id, 'gross_g' => '1010', 'tare_g' => '10'])->assertCreated();
        $this->assertSame('1000.000000', InventoryWeighing::latest('id')->first()->net_g);
        $this->assertSame('7.000000', $balance->fresh()->units);
        $this->assertSame('1000.000000', $balance->fresh()->grams);
        $this->assertDatabaseCount('inventory_movements', 1);
        $operations = InventoryOperation::count();
        $this->command('weigh', ['balance_id' => $balance->public_id, 'units' => '1', 'gross_g' => '1010', 'tare_g' => '10'])->assertUnprocessable()->assertJsonValidationErrors('units');
        $this->assertSame($operations, InventoryOperation::count());
        $this->assertDatabaseCount('inventory_weighings', 2);
    }

    public function test_receiving_is_exact_partial_audited_and_idempotent(): void
    {
        $item = $this->order();
        $key = (string) Str::uuid();
        $payload = ['order_item_id' => $item->public_id, 'location_id' => $this->location->public_id, 'quantity' => '1.25', 'received_on' => '2026-11-08', 'expires_on' => '2026-12-01', 'supplier_lot_code' => 'FICTICIO-01', 'quality_status' => 'released', 'gross_g' => '1260', 'tare_g' => '10'];
        $op = $this->command('receive', $payload, $key)->assertCreated()->json('data.public_id');
        $this->command('receive', $payload, $key)->assertCreated()->assertJsonPath('data.public_id', $op);
        $this->assertDatabaseCount('purchase_receipts', 1);
        $this->assertSame('1250.000000', InventoryBalance::first()->grams);
        $this->assertSame('0.000000', InventoryBalance::first()->units);
        $this->assertSame('1.250000', $item->fresh()->received_quantity);
        $this->assertSame('15.4320', PurchaseReceipt::first()->total_cost);
        $this->assertSame('1250.000000', InventoryWeighing::first()->net_g);
        $this->command('receive', [...$payload, 'quantity' => '2'], $key)->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');
        $this->command('receive', [...$payload, 'quantity' => '11', 'gross_g' => '11010'])->assertUnprocessable();
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->getJson($this->base.'/inventory/positions')->assertOk()->assertJsonPath('data.0.grams', '1250.000000')->assertJsonMissingPath('data.0.company_id')->assertJsonMissingPath('data.0.id');
    }

    public function test_fefo_skips_expired_blocked_and_quarantined_and_splits_released_lots(): void
    {
        $item = $this->order();
        $later = $this->receipt($item, '3', 'released', '2026-12-10');
        $earlier = $this->receipt($item, '1', 'released', '2026-11-20');
        $this->receipt($item, '0.5', 'quarantine', '2026-11-09');
        $this->receipt($item, '0.5', 'blocked', '2026-11-10');
        $this->receipt($item, '0.5', 'quarantine', '2026-11-01');
        $id = $this->reservationId(['grams' => '2500']);
        $lines = InventoryAllocation::where('reservation_id', InventoryReservation::where('public_id', $id)->value('id'))->orderBy('id')->get();
        $this->assertSame([$earlier->id, $later->id], $lines->pluck('balance_id')->all());
        $this->assertSame(['1000.000000', '1500.000000'], $lines->pluck('grams')->all());
        $this->assertSame('1000.000000', $earlier->fresh()->reserved_grams);
        $before = InventoryOperation::count();
        $this->command('reserve', ['variant_id' => $this->variant->public_id, 'warehouse_id' => Warehouse::first()->public_id, 'grams' => '2000'])->assertUnprocessable();
        $this->assertSame($before, InventoryOperation::count());
        $this->assertSame('1500.000000', $later->fresh()->reserved_grams);
    }

    public function test_consumption_rechecks_quality_and_uses_measured_mass_once(): void
    {
        $balance = $this->receipt($this->order());
        $id = $this->reservationId(['grams' => '1250']);
        $reservation = InventoryReservation::where('public_id', $id)->first();
        $line = InventoryAllocation::where('reservation_id', $reservation->id)->first();
        $lot = InventoryLot::find($balance->lot_id);
        $this->command('quality', ['lot_id' => $lot->public_id, 'quality_status' => 'blocked'])->assertCreated();
        $measurements = [['allocation_id' => $line->public_id, 'gross_g' => '1260', 'tare_g' => '10']];
        $this->command('consume', ['reservation_id' => $id, 'measurements' => $measurements])->assertUnprocessable();
        $this->assertSame('1250.000000', $balance->fresh()->reserved_grams);
        $this->command('quality', ['lot_id' => $lot->public_id, 'quality_status' => 'released'])->assertCreated();
        $this->command('consume', ['reservation_id' => $id])->assertUnprocessable();
        $key = (string) Str::uuid();
        $op = $this->command('consume', ['reservation_id' => $id, 'measurements' => $measurements], $key)->assertCreated()->json('data.public_id');
        $this->command('consume', ['reservation_id' => $id, 'measurements' => $measurements], $key)->assertCreated()->assertJsonPath('data.public_id', $op);
        $this->assertSame('750.000000', $balance->fresh()->grams);
        $this->assertSame('0.000000', $balance->fresh()->reserved_grams);
        $this->command('consume', ['reservation_id' => $id, 'measurements' => $measurements])->assertUnprocessable();
    }

    public function test_transfer_count_and_reversal_preserve_original_movements_and_holds(): void
    {
        $balance = $this->receipt($this->order());
        $destination = $this->record(Location::class, ['warehouse_id' => $this->location->warehouse_id, 'name' => 'Destino FICTÍCIO', 'code' => 'DST']);
        $op = $this->command('transfer', ['balance_id' => $balance->public_id, 'to_location_id' => $destination->public_id, 'gross_g' => '510', 'tare_g' => '10'])->assertCreated()->json('data.public_id');
        $this->assertSame('1500.000000', $balance->fresh()->grams);
        $this->assertSame('500.000000', InventoryBalance::where('location_id', $destination->id)->first()->grams);
        $operation = InventoryOperation::where('public_id', $op)->first();
        $this->assertSame('0.000000', InventoryMovement::where('operation_id', $operation->id)->get()->reduce(fn ($sum, $m) => bcadd($sum, $m->grams_delta, 6), '0.000000'));
        $this->command('reverse', ['operation_id' => $op])->assertCreated();
        $this->assertSame('2000.000000', $balance->fresh()->grams);
        $this->command('reverse', ['operation_id' => $op])->assertUnprocessable();
        $count = $this->command('count', ['balance_id' => $balance->public_id, 'gross_g' => '1810', 'tare_g' => '10'])->assertCreated()->json('data.public_id');
        $this->assertSame('1800.000000', $balance->fresh()->grams);
        $this->reservationId(['grams' => '1700']);
        $this->command('count', ['balance_id' => $balance->public_id, 'gross_g' => '1600', 'tare_g' => '0'])->assertUnprocessable();
        $this->assertSame('1800.000000', $balance->fresh()->grams);
        $this->command('reverse', ['operation_id' => $count])->assertCreated();
        $this->assertSame('2000.000000', $balance->fresh()->grams);
        $this->patchJson($this->base.'/inventory/movements/'.InventoryMovement::first()->public_id, ['grams_delta' => '1'])->assertStatus(405);
    }

    public function test_receipt_reversal_requires_available_stock_and_restores_purchase_remaining(): void
    {
        $item = $this->order();
        $balance = $this->receipt($item);
        $receipt = PurchaseReceipt::first();
        $op = InventoryOperation::find($receipt->operation_id);
        $reservation = $this->reservationId(['grams' => '100']);
        $this->command('reverse', ['operation_id' => $op->public_id])->assertUnprocessable();
        $this->command('cancel-reservation', ['reservation_id' => $reservation])->assertCreated();
        $this->command('reverse', ['operation_id' => $op->public_id])->assertCreated();
        $this->assertSame('0.000000', $balance->fresh()->grams);
        $this->assertSame('0.000000', $item->fresh()->received_quantity);
        $this->assertDatabaseCount('purchase_receipts', 1);
        $this->assertDatabaseCount('inventory_lots', 1);
        $this->assertDatabaseCount('inventory_movements', 2);
    }

    public function test_permissions_hide_costs_and_separate_initial_quality_approval(): void
    {
        $item = $this->order();
        $this->receipt($item);
        $user = User::factory()->create();
        $user->companies()->attach($this->company, ['role_id' => DB::table('roles')->where('code', 'warehouse')->value('id')]);
        $this->actingAs($user);
        $this->getJson($this->base.'/purchasing/order-items')->assertOk()->assertJsonMissingPath('data.0.unit_cost')->assertJsonMissingPath('data.0.total_cost');
        $this->getJson($this->base.'/purchasing/receipts')->assertOk()->assertJsonMissingPath('data.0.unit_cost');
        $this->postJson($this->base.'/purchasing/suppliers', ['name' => 'Não autorizado', 'reason' => 'Teste FICTÍCIO'])->assertForbidden();
        $this->command('receive', ['order_item_id' => $item->public_id, 'location_id' => $this->location->public_id, 'quantity' => '1', 'received_on' => '2026-11-08', 'expires_on' => '2026-12-01', 'supplier_lot_code' => 'FICTICIO', 'quality_status' => 'released', 'gross_g' => '1000', 'tare_g' => '0'])->assertForbidden();
        $this->command('quality', ['lot_id' => InventoryLot::first()->public_id, 'quality_status' => 'released'])->assertForbidden();
        $this->assertDatabaseCount('purchase_receipts', 1);
        $this->actingAs($this->owner)->getJson($this->base.'/purchasing/order-items')->assertJsonPath('data.0.unit_cost', '12.3456');
    }

    public function test_dual_policy_never_derives_mass_from_nominal_weight(): void
    {
        $this->policy->forceFill(['mode' => 'dual'])->save();
        $item = $this->order('10', 'UN');
        $balance = $this->receipt($item, '2', 'released', '2026-12-01', ['units' => '2', 'gross_g' => '710', 'tare_g' => '10']);
        $this->assertSame('2.000000', $balance->units);
        $this->assertSame('700.000000', $balance->grams);
        $this->command('reserve', ['variant_id' => $this->variant->public_id, 'warehouse_id' => Warehouse::first()->public_id, 'units' => '1', 'grams' => '300'])->assertUnprocessable()->assertJsonValidationErrors('balance_id');
        $id = $this->reservationId(['balance_id' => $balance->public_id, 'units' => '1', 'grams' => '300']);
        $this->command('cancel-reservation', ['reservation_id' => $id])->assertCreated();
        $this->command('weigh', ['balance_id' => $balance->public_id, 'gross_g' => '310', 'tare_g' => '10'])->assertCreated();
        $this->assertSame('700.000000', $balance->fresh()->grams);
        $this->command('issue', ['balance_id' => $balance->public_id, 'units' => '1', 'gross_g' => '310', 'tare_g' => '10'])->assertCreated();
        $this->assertSame('400.000000', $balance->fresh()->grams);
        $this->assertSame('1.000000', $balance->fresh()->units);
    }

    public function test_invalid_tare_precision_and_cross_tenant_references_are_rejected(): void
    {
        $balance = $this->receipt($this->order());
        $this->command('issue', ['balance_id' => $balance->public_id, 'gross_g' => '1', 'tare_g' => '2'])->assertUnprocessable();
        $this->command('issue', ['balance_id' => $balance->public_id, 'gross_g' => 1.25, 'tare_g' => '0'])->assertUnprocessable();
        $this->command('issue', ['balance_id' => $balance->public_id, 'gross_g' => '1.0000001', 'tare_g' => '0'])->assertUnprocessable();
        $other = Company::create(['trade_name' => 'Outra FICTÍCIA', 'legal_name' => 'Outra FICTÍCIA']);
        $foreign = new Supplier;
        $foreign->forceFill(['company_id' => $other->id, 'name' => 'Fornecedor sigiloso FICTÍCIO'])->save();
        $this->command('order', ['supplier_id' => $foreign->public_id, 'items' => [['variant_id' => $this->variant->public_id, 'unit_id' => Unit::where('code', 'KG')->first()->public_id, 'quantity' => '1', 'unit_cost' => '1']]])->assertNotFound();
        $this->getJson('/api/v1/companies/'.$other->public_id.'/inventory/positions')->assertForbidden();
        $this->getJson($this->base.'/purchasing/suppliers/'.$foreign->public_id)->assertNotFound();
        $this->assertSame('2000.000000', $balance->fresh()->grams);
    }

    public function test_first_lot_locks_variant_and_inventory_policy_without_requiring_a_sku(): void
    {
        $this->variant = $this->record(ProductVariant::class, ['name' => 'Variante sem SKU FICTÍCIA', 'product_id' => $this->variant->product_id, 'flavor' => 'Original']);
        $this->policy = $this->record(InventoryPolicy::class, ['variant_id' => $this->variant->id, 'mode' => 'mass', 'requires_expiry' => true]);
        $this->receipt($this->order());
        $this->getJson($this->base.'/product-variants/'.$this->variant->public_id)->assertOk()->assertJsonPath('data.identity_locked', true);
        $this->patchJson($this->base.'/product-variants/'.$this->variant->public_id, ['flavor' => 'Outro'])->assertUnprocessable();
        $this->patchJson($this->base.'/inventory/policies/'.$this->policy->public_id, ['mode' => 'count', 'reason' => 'Mudança inválida FICTÍCIA'])->assertUnprocessable();
        $this->patchJson($this->base.'/inventory/policies/'.$this->policy->public_id, ['requires_expiry' => false, 'reason' => 'Mudança inválida FICTÍCIA'])->assertUnprocessable();
    }

    public function test_no_expiry_requires_explicit_policy_and_is_after_dated_lots_in_fefo(): void
    {
        $this->patchJson($this->base.'/inventory/policies/'.$this->policy->public_id, ['requires_expiry' => false, 'reason' => 'Política de teste FICTÍCIA'])->assertOk();
        $item = $this->order();
        $undated = $this->receipt($item, '1', 'released', '2026-12-01', ['expires_on' => null, 'no_expiry_reason' => 'Ausência de validade declarada somente para teste FICTÍCIO']);
        $dated = $this->receipt($item, '1', 'released', '2026-11-09');
        $reservation = $this->reservationId(['grams' => '500']);
        $line = InventoryAllocation::where('reservation_id', InventoryReservation::where('public_id', $reservation)->value('id'))->first();
        $this->assertSame($dated->id, $line->balance_id);
        $this->assertSame('0.000000', $undated->fresh()->reserved_grams);
    }

    public function test_quality_audit_cannot_reveal_supplier_contacts_or_purchase_reasons(): void
    {
        $supplier = $this->postJson($this->base.'/purchasing/suppliers', ['name' => 'Contato restrito FICTÍCIO', 'email' => 'privado@example.test', 'reason' => 'Cadastro de teste FICTÍCIO'])->assertCreated()->json('data.public_id');
        $this->order();
        $event = InventoryEvent::where('resource_public_id', $supplier)->first();
        $orderOp = InventoryOperation::where('kind', 'order')->first();
        $user = User::factory()->create();
        $user->companies()->attach($this->company, ['role_id' => DB::table('roles')->where('code', 'quality')->value('id')]);
        $this->actingAs($user);
        $this->getJson($this->base.'/inventory/events')->assertOk()->assertDontSee('privado@example.test');
        $this->getJson($this->base.'/inventory/events/'.$event->public_id)->assertForbidden();
        $this->getJson($this->base.'/inventory/operations/'.$orderOp->public_id)->assertForbidden();
        $this->getJson($this->base.'/purchasing/suppliers')->assertForbidden();
    }

    public function test_count_policy_uses_units_and_keeps_ledger_equal_to_balance(): void
    {
        $this->policy->forceFill(['mode' => 'count'])->save();
        $item = $this->order('10', 'UN');
        $balance = $this->receipt($item, '2', 'released', '2026-12-01', ['units' => '2', 'gross_g' => null, 'tare_g' => null]);
        $this->command('count', ['balance_id' => $balance->public_id, 'units' => '0'])->assertCreated();
        $this->command('issue', ['balance_id' => $balance->public_id, 'units' => '1'])->assertUnprocessable();
        $this->command('count', ['balance_id' => $balance->public_id, 'units' => '3'])->assertCreated();
        $sum = InventoryMovement::where('balance_id', $balance->id)->get()->reduce(fn ($sum, $m) => bcadd($sum, $m->units_delta, 6), '0.000000');
        $this->assertSame('3.000000', $sum);
        $this->assertSame($sum, $balance->fresh()->units);
        $this->assertSame('0.000000', $balance->fresh()->grams);
        $this->getJson($this->base.'/inventory/positions?variant_id='.$this->variant->public_id.'&quality_status=blocked')->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson($this->base.'/inventory/positions?variant_id='.$this->variant->public_id.'&warehouse_id='.Warehouse::first()->public_id.'&quality_status=released')->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_count_reservation_rejects_mass_instead_of_discarding_it(): void
    {
        $this->policy->forceFill(['mode' => 'count'])->save();
        $balance = $this->receipt($this->order('10', 'UN'), '2', extra: ['units' => '2', 'gross_g' => null, 'tare_g' => null]);
        $reservation = InventoryReservation::where('public_id', $this->reservationId(['units' => '1']))->firstOrFail();
        $allocation = InventoryAllocation::where('reservation_id', $reservation->id)->firstOrFail();
        $operations = InventoryOperation::count();
        $key = (string) Str::uuid();
        foreach ([['10', '20'], ['20', '10']] as [$gross, $tare]) {
            $this->command('consume', ['reservation_id' => $reservation->public_id, 'measurements' => [['allocation_id' => $allocation->public_id, 'gross_g' => $gross, 'tare_g' => $tare]]], $key)->assertUnprocessable()->assertJsonValidationErrors('measurements');
        }
        $this->assertSame($operations, InventoryOperation::count());
        $this->assertSame('active', $reservation->fresh()->status);
        $this->assertSame('2.000000', $balance->fresh()->units);
        $this->assertSame('1.000000', $balance->fresh()->reserved_units);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertDatabaseCount('inventory_weighings', 0);
        $this->command('consume', ['reservation_id' => $reservation->public_id], $key)->assertCreated();
        $this->assertSame('consumed', $reservation->fresh()->status);
        $this->assertSame('1.000000', $balance->fresh()->units);
        $this->assertSame('0.000000', $balance->fresh()->reserved_units);
        $this->assertDatabaseCount('inventory_movements', 2);
    }

    public function test_inventory_schema_and_composite_fk_reject_cross_tenant_position(): void
    {
        $balance = $this->receipt($this->order());
        if (DB::getDriverName() === 'mysql') {
            $tables = DB::select("SELECT ENGINE,TABLE_COLLATION FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('inventory_operations','suppliers','inventory_policies','locations','purchase_orders','purchase_order_items','inventory_lots','inventory_balances','purchase_receipts','inventory_reservations','inventory_allocations','inventory_movements','inventory_weighings','inventory_counts','inventory_events')");
            $this->assertCount(15, $tables);
            foreach ($tables as $table) {
                $this->assertSame('InnoDB', $table->ENGINE);
                $this->assertStringStartsWith('utf8mb4', $table->TABLE_COLLATION);
            }
            $columns = DB::select("SELECT DATA_TYPE,NUMERIC_PRECISION,NUMERIC_SCALE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='inventory_balances' AND COLUMN_NAME IN ('units','grams','reserved_units','reserved_grams')");
            $this->assertCount(4, $columns);
            foreach ($columns as $column) {
                $this->assertSame('decimal', $column->DATA_TYPE);
                $this->assertSame(18, (int) $column->NUMERIC_PRECISION);
                $this->assertSame(6, (int) $column->NUMERIC_SCALE);
            }
        }
        $other = Company::create(['trade_name' => 'Outra FICTÍCIA', 'legal_name' => 'Outra FICTÍCIA']);
        $factory = DB::table('factories')->insertGetId(['company_id' => $other->id, 'public_id' => (string) Str::ulid(), 'name' => 'Outra fábrica FICTÍCIA', 'timezone' => 'America/Bahia']);
        $warehouse = DB::table('warehouses')->insertGetId(['company_id' => $other->id, 'public_id' => (string) Str::ulid(), 'name' => 'Outro depósito FICTÍCIO', 'factory_id' => $factory, 'kind' => 'general']);
        $location = DB::table('locations')->insertGetId(['company_id' => $other->id, 'public_id' => (string) Str::ulid(), 'name' => 'Outro local FICTÍCIO', 'warehouse_id' => $warehouse, 'code' => 'OUTRO']);
        $this->expectException(QueryException::class);
        DB::table('inventory_balances')->insert(['company_id' => $this->company->id, 'public_id' => (string) Str::ulid(), 'lot_id' => $balance->lot_id, 'location_id' => $location, 'units' => '0', 'grams' => '0', 'reserved_units' => '0', 'reserved_grams' => '0']);
    }
}
