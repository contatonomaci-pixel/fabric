<?php

use App\Domain\Catalog\CatalogService;
use App\Models\Company;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

// Worker exclusivo do teste concorrente; não é endpoint nem comando de operação.
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || config('database.default') !== 'mysql'
    || ! str_ends_with(config('database.connections.mysql.database'), '_test')
    || ! in_array(config('database.connections.mysql.host'), ['127.0.0.1', 'localhost'], true)) {
    fwrite(STDERR, "Worker permitido somente em MySQL local de testes.\n");
    exit(2);
}
$barrier = getenv('CATALOG_TEST_BARRIER');
file_put_contents($barrier.'/ready-'.getmypid(), 'ready');
$deadline = microtime(true) + 15;
while (count(glob($barrier.'/ready-*')) < 2) {
    if (microtime(true) > $deadline) {
        exit(3);
    }
    usleep(10000);
}
$company = Company::where('public_id', getenv('CATALOG_TEST_COMPANY'))->firstOrFail();
$actor = User::where('public_id', getenv('CATALOG_TEST_ACTOR'))->firstOrFail();
try {
    $record = app(CatalogService::class)->save($actor, $company, 'commercial-skus', [
        'name' => 'Sessão concorrente FICTÍCIA',
        'variant_id' => getenv('CATALOG_TEST_VARIANT'),
        'unit_id' => getenv('CATALOG_TEST_UNIT'),
        'sku' => getenv('CATALOG_TEST_SKU') ?: null,
    ]);
    echo json_encode(['result' => 'created', 'sku' => $record->sku], JSON_THROW_ON_ERROR);
} catch (ValidationException $error) {
    echo json_encode(['result' => 'validation', 'fields' => array_keys($error->errors())], JSON_THROW_ON_ERROR);
}
