<?php

namespace Tests\Feature;

use App\Domain\Catalog\CodeReservation;
use App\Domain\Catalog\CommercialSku;
use App\Domain\Catalog\ProductBarcode;
use App\Domain\Catalog\ProductVariant;
use App\Domain\Catalog\Unit;
use App\Models\Company;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class CatalogConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('TEST_DB_CONNECTION') !== 'mysql') {
            $this->markTestSkipped('Concorrência exige MySQL 8.4 real e processos independentes.');
        }
        config(['demo.password' => 'Ficticia-Segura123!']);
        $this->seed(DemoSeeder::class);
    }

    private function concurrently(?string $customSku): array
    {
        $company = Company::firstOrFail();
        $alice = User::where('email', 'demo@example.test')->firstOrFail();
        $bob = User::factory()->create();
        $bob->companies()->attach($company, ['role_id' => DB::table('roles')->where('code', 'owner')->value('id')]);
        $barrier = sys_get_temp_dir().'/fc-catalog-'.bin2hex(random_bytes(12));
        mkdir($barrier, 0700);
        $processes = [];
        try {
            foreach ([$alice, $bob] as $actor) {
                $process = new Process([PHP_BINARY, base_path('tests/support/reserve-catalog-code.php')], base_path(), [
                    'APP_ENV' => 'testing', 'APP_CONFIG_CACHE' => $barrier.'/unused.php', 'DB_CONNECTION' => 'mysql', 'DB_URL' => '',
                    'DB_DATABASE' => config('database.connections.mysql.database'),
                    'CATALOG_TEST_COMPANY' => $company->public_id, 'CATALOG_TEST_ACTOR' => $actor->public_id,
                    'CATALOG_TEST_VARIANT' => ProductVariant::first()->public_id, 'CATALOG_TEST_UNIT' => Unit::where('code', 'UN')->first()->public_id,
                    'CATALOG_TEST_SKU' => $customSku ?? '', 'CATALOG_TEST_BARRIER' => $barrier,
                ]);
                $process->setTimeout(30)->start();
                $processes[] = $process;
            }
            $results = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
                $results[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            }

            return $results;
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            foreach (glob($barrier.'/*') as $file) {
                unlink($file);
            }
            rmdir($barrier);
        }
    }

    public function test_two_independent_sessions_generate_distinct_reserved_identifiers(): void
    {
        $results = $this->concurrently(null);
        $this->assertSame(['created', 'created'], array_column($results, 'result'));
        $this->assertCount(2, array_unique(array_column($results, 'sku')));
        $this->assertDatabaseCount('commercial_skus', 9);
        $this->assertDatabaseCount('code_reservations', 18);
        $this->assertSame(9, ProductBarcode::distinct()->count('code'));
    }

    public function test_simultaneous_manual_duplicate_has_only_one_winner(): void
    {
        $results = $this->concurrently('FICTICIO-CONCORRENTE');
        $this->assertSame(1, count(array_filter($results, fn ($r) => $r['result'] === 'created')));
        $this->assertSame(1, count(array_filter($results, fn ($r) => $r['result'] === 'validation' && $r['fields'] === ['sku'])));
        $this->assertSame(1, CommercialSku::where('sku', 'FICTICIO-CONCORRENTE')->count());
        $this->assertSame(1, CodeReservation::where('namespace', 'sku')->where('code', 'FICTICIO-CONCORRENTE')->count());
    }
}
