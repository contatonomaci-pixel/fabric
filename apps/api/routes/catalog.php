<?php

use App\Domain\Catalog\CatalogDefinition;
use App\Http\Controllers\Api\V1\BarcodeController;
use App\Http\Controllers\Api\V1\CatalogController;
use Illuminate\Support\Facades\Route;

Route::prefix('companies/{company}')->group(function () {
    foreach (array_keys(CatalogDefinition::MODELS) as $resource) {
        Route::get($resource, [CatalogController::class, 'index'])->defaults('resource', $resource);
        Route::post($resource, [CatalogController::class, 'store'])->defaults('resource', $resource);
        Route::get($resource.'/{record}', [CatalogController::class, 'show'])->defaults('resource', $resource)->whereUlid('record');
        Route::patch($resource.'/{record}', [CatalogController::class, 'update'])->defaults('resource', $resource)->whereUlid('record');
    }
    Route::post('barcodes/reserve', [BarcodeController::class, 'reserve']);
    Route::post('barcodes/validate', [BarcodeController::class, 'validateCode']);
    Route::get('catalog-history', [BarcodeController::class, 'history']);
    Route::get('commercial-skus/{record}/barcode.{format}', [BarcodeController::class, 'export'])->whereUlid('record')->where('format', 'svg|png|pdf');
});
