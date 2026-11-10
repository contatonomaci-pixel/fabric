<?php

use App\Domain\Inventory\InventoryAccess;
use App\Domain\Inventory\InventoryRead;
use App\Http\Controllers\Api\V1\InventoryController;
use Illuminate\Support\Facades\Route;

Route::prefix('companies/{company}')->group(function () {
    foreach (array_keys(InventoryRead::MODELS) as $resource) {
        $path = (in_array($resource, ['suppliers', 'orders', 'order-items', 'receipts'], true) ? 'purchasing/' : 'inventory/').$resource;
        Route::get($path, [InventoryController::class, 'index'])->defaults('resource', $resource);
        Route::get($path.'/{record}', [InventoryController::class, 'show'])->defaults('resource', $resource)->whereUlid('record');
        if (in_array($resource, ['suppliers', 'locations', 'policies'], true)) {
            Route::post($path, [InventoryController::class, 'store'])->defaults('resource', $resource);
            Route::patch($path.'/{record}', [InventoryController::class, 'update'])->defaults('resource', $resource)->whereUlid('record');
        }
    }
    foreach (array_keys(InventoryAccess::COMMANDS) as $kind) {
        Route::post('inventory/actions/'.$kind, [InventoryController::class, 'command'])->defaults('kind', $kind);
    }
});
