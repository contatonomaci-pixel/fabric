<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json(['name' => 'FactoryControl PRO API', 'version' => 'v1']));

require __DIR__.'/auth.php';
