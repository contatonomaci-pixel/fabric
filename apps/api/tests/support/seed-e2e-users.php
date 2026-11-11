<?php

use App\Models\Company;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$connection = config('database.default');
$database = config('database.connections.'.$connection.'.database');
$databaseDir = str_replace(DIRECTORY_SEPARATOR, '/', realpath(dirname($database)) ?: '');
$temporaryRoot = str_replace(DIRECTORY_SEPARATOR, '/', realpath(sys_get_temp_dir()));
$localSqlite = $connection === 'sqlite' && str_starts_with($databaseDir, $temporaryRoot.'/factorycontrol-e2e-');
$localMysql = $connection === 'mysql' && str_ends_with($database, '_test') && in_array(config('database.connections.mysql.host'), ['127.0.0.1', 'localhost'], true);
if (! $app->environment(['local', 'testing']) || (! $localSqlite && ! $localMysql)) {
    throw new RuntimeException('Contas E2E permitidas somente no banco local descartável do runner.');
}
$company = Company::where('is_demo', true)->firstOrFail();
$password = config('demo.password');
if (! is_string($password) || strlen($password) < 12) {
    throw new RuntimeException('Senha E2E efêmera ausente.');
}
foreach (['desktop', 'mobile'] as $project) {
    $inventory = User::firstOrCreate(['email' => 'inventory-'.$project.'@example.test'], ['name' => 'Usuário estoque '.$project.' FICTÍCIO', 'password' => $password]);
    $inventory->forceFill(['is_demo' => true])->save();
    $inventory->companies()->syncWithoutDetaching([$company->id => ['role_id' => DB::table('roles')->where('code', 'owner')->value('id')]]);
    $user = User::firstOrCreate(['email' => 'catalog-'.$project.'@example.test'], ['name' => 'Usuário catálogo '.$project.' FICTÍCIO', 'password' => $password]);
    $user->forceFill(['is_demo' => true])->save();
    $user->companies()->syncWithoutDetaching([$company->id => ['role_id' => DB::table('roles')->where('code', 'owner')->value('id')]]);
}
