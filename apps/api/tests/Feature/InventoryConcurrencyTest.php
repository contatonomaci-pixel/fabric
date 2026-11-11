<?php

namespace Tests\Feature;

use App\Domain\Catalog\ProductVariant;
use App\Domain\Catalog\Unit;
use App\Domain\Catalog\Warehouse;
use App\Domain\Inventory\InventoryBalance;
use App\Domain\Inventory\InventoryOperation;
use App\Domain\Inventory\InventoryPolicy;
use App\Domain\Inventory\Location;
use App\Domain\Purchasing\PurchaseOrderItem;
use App\Domain\Purchasing\Supplier;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class InventoryConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('TEST_DB_CONNECTION') !== 'mysql') {
            $this->markTestSkipped('Concorrência de estoque exige MySQL real e dois processos independentes.');
        }
        config(['demo.password' => 'Concorrencia-FICTICIA123!']);
        $this->seed(DemoSeeder::class);
    }

    private function concurrent(string $kind, bool $sameKey = false): array
    {
        $company = Company::firstOrFail();
        $alice = User::where('email', 'demo@example.test')->firstOrFail();
        $bob = User::factory()->create();
        $bob->companies()->attach($company, ['role_id' => DB::table('roles')->where('code', 'owner')->value('id')]);
        $variant = ProductVariant::first();
        $policy = new InventoryPolicy;
        $policy->forceFill(['company_id' => $company->id, 'variant_id' => $variant->id, 'mode' => 'count', 'requires_expiry' => true])->save();
        $base = '/api/v1/companies/'.$company->public_id.'/inventory/actions/';
        $this->actingAs($alice);
        $this->postJson($base.'order', ['reason' => 'Compra FICTÍCIA concorrente', 'idempotency_key' => (string) Str::uuid(), 'supplier_id' => Supplier::first()->public_id, 'items' => [['variant_id' => $variant->public_id, 'unit_id' => Unit::where('code', 'UN')->first()->public_id, 'quantity' => '10', 'unit_cost' => '1']]])->assertCreated();
        $this->postJson($base.'receive', ['reason' => 'Recebimento FICTÍCIO concorrente', 'idempotency_key' => (string) Str::uuid(), 'order_item_id' => PurchaseOrderItem::first()->public_id, 'location_id' => Location::first()->public_id, 'quantity' => '10', 'units' => '10', 'received_on' => now($company->timezone)->toDateString(), 'expires_on' => now($company->timezone)->addDays(10)->toDateString(), 'supplier_lot_code' => 'FICTICIO-CONCORRENCIA', 'quality_status' => 'released'])->assertCreated();
        $balance = InventoryBalance::first();
        $barrier = sys_get_temp_dir().'/fc-inventory-'.bin2hex(random_bytes(12));
        mkdir($barrier, 0700);
        $processes = [];
        $key = (string) Str::uuid();
        try {
            foreach ([$alice, $bob] as $actor) {
                $input = ['reason' => 'Solicitação concorrente FICTÍCIA', 'idempotency_key' => $sameKey ? $key : (string) Str::uuid(), 'units' => '7'];
                $input += $kind === 'reserve' ? ['variant_id' => $variant->public_id, 'warehouse_id' => Warehouse::first()->public_id] : ['balance_id' => $balance->public_id];
                $process = new Process([PHP_BINARY, base_path('tests/support/operate-inventory.php')], base_path(), ['APP_ENV' => 'testing', 'APP_CONFIG_CACHE' => $barrier.'/unused.php', 'DB_CONNECTION' => 'mysql', 'DB_URL' => '', 'DB_DATABASE' => config('database.connections.mysql.database'), 'INVENTORY_TEST_COMPANY' => $company->public_id, 'INVENTORY_TEST_ACTOR' => $actor->public_id, 'INVENTORY_TEST_KIND' => $kind, 'INVENTORY_TEST_INPUT' => json_encode($input, JSON_THROW_ON_ERROR), 'INVENTORY_TEST_BARRIER' => $barrier]);
                $process->setTimeout(30)->start();
                $processes[] = $process;
            }
            $results = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
                $results[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            }

            return [$results, $balance->fresh()];
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }foreach (glob($barrier.'/*') as $file) {
                unlink($file);
            }rmdir($barrier);
        }
    }

    public function test_two_simultaneous_reservations_cannot_overbook_stock(): void
    {
        [$results,$balance] = $this->concurrent('reserve');
        $this->assertSame(1, count(array_filter($results, fn ($r) => $r['result'] === 'created')));
        $this->assertSame('10.000000', $balance->units);
        $this->assertSame('7.000000', $balance->reserved_units);
    }

    public function test_two_simultaneous_issues_cannot_make_negative_stock(): void
    {
        [$results,$balance] = $this->concurrent('issue');
        $this->assertSame(1, count(array_filter($results, fn ($r) => $r['result'] === 'created')));
        $this->assertSame('3.000000', $balance->units);
        $this->assertSame('0.000000', $balance->reserved_units);
    }

    public function test_simultaneous_retries_have_one_physical_effect(): void
    {
        [$results,$balance] = $this->concurrent('issue', true);
        $this->assertSame(['created', 'created'], array_column($results, 'result'));
        $this->assertSame(1, count(array_unique(array_column($results, 'operation'))));
        $this->assertSame('3.000000', $balance->units);
        $this->assertSame(1, InventoryOperation::where('kind', 'issue')->count());
    }
}
