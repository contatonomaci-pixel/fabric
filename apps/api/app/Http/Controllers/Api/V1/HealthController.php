<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class HealthController extends Controller
{
    public function __invoke()
    {
        $checks = ['database' => false, 'redis' => false];
        foreach (array_keys($checks) as $name) {
            try {
                if ($name === 'database') {
                    DB::select('SELECT 1');
                } else {
                    Redis::connection()->ping();
                } $checks[$name] = true;
            } catch (\Throwable) {
                Log::warning('Readiness check failed.', ['dependency' => $name]);
            }
        }

        return ApiResponse::data(['status' => in_array(false, $checks, true) ? 'unavailable' : 'ok', 'checks' => $checks], in_array(false, $checks, true) ? 503 : 200);
    }
}
