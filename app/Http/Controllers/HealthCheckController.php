<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HealthCheckController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => false,
            'cache' => false,
            'storage' => false,
        ];

        try {
            DB::select('SELECT 1');
            $checks['database'] = true;
        } catch (\Throwable) {
            $checks['database'] = false;
        }

        try {
            Cache::put('health:probe', 'ok', 10);
            $checks['cache'] = Cache::get('health:probe') === 'ok';
            Cache::forget('health:probe');
        } catch (\Throwable) {
            $checks['cache'] = false;
        }

        $privatePath = storage_path('app/private');
        $checks['storage'] = is_dir($privatePath) && is_writable($privatePath);

        $healthy = ! in_array(false, $checks, true);

        return response()->json([
            'status' => $healthy ? 'ok' : 'degraded',
            'checks' => $checks,
            'timestamp' => now()->toIso8601String(),
        ], $healthy ? 200 : 503);
    }
}
