<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Throwable;

class DiagnosisController extends Controller
{
    /**
     * Handle the system diagnosis request.
     */
    public function __invoke(Request $request): JsonResponse|Response
    {
        $startTime = microtime(true);

        // 1. PHP & Laravel Information
        $phpVersion = PHP_VERSION;
        $laravelVersion = app()->version();
        $environment = app()->environment();
        $debugMode = config('app.debug');
        $sapi = php_sapi_name();
        $serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? $sapi;

        // 2. OPcache Status
        $opcacheEnabled = function_exists('opcache_get_status') && is_array(opcache_get_status(false));

        // 3. Essential Extensions
        $requiredExtensions = ['bcmath', 'pdo_pgsql', 'zip', 'mbstring', 'intl', 'curl'];
        $extensions = [];
        foreach ($requiredExtensions as $ext) {
            $extensions[$ext] = extension_loaded($ext);
        }

        // 4. Database Connectivity Check (Safe, without exposing credentials)
        $dbStatus = [
            'primary_db' => [
                'connection' => 'pgsql',
                'database' => config('database.connections.pgsql.database'),
                'schema' => config('database.connections.pgsql.search_path'),
                'status' => 'unknown',
            ],
            'imedx_db' => [
                'connection' => 'pgsql_imedx',
                'database' => config('database.connections.pgsql_imedx.database'),
                'schema' => config('database.connections.pgsql_imedx.search_path'),
                'status' => 'unknown',
            ],
        ];

        try {
            DB::connection('pgsql')->selectOne('SELECT 1');
            $dbStatus['primary_db']['status'] = 'connected';
        } catch (Throwable $e) {
            $dbStatus['primary_db']['status'] = 'disconnected';
            $dbStatus['primary_db']['error'] = $debugMode ? $e->getMessage() : 'Connection timeout or error';
        }

        try {
            DB::connection('pgsql_imedx')->selectOne('SELECT 1');
            $dbStatus['imedx_db']['status'] = 'connected';
        } catch (Throwable $e) {
            $dbStatus['imedx_db']['status'] = 'disconnected';
            $dbStatus['imedx_db']['error'] = $debugMode ? $e->getMessage() : 'Connection timeout or error';
        }

        // 5. Memory & Execution metrics
        $executionTimeMs = round((microtime(true) - $startTime) * 1000, 2);
        $memoryUsage = round(memory_get_usage() / 1024 / 1024, 2);
        $memoryPeak = round(memory_get_peak_usage() / 1024 / 1024, 2);

        $data = [
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
            'system' => [
                'php_version' => $phpVersion,
                'laravel_version' => $laravelVersion,
                'environment' => $environment,
                'debug' => $debugMode,
                'server_software' => $serverSoftware,
                'sapi' => $sapi,
                'timezone' => config('app.timezone'),
            ],
            'performance' => [
                'opcache_enabled' => $opcacheEnabled,
                'memory_usage_mb' => $memoryUsage,
                'memory_peak_mb' => $memoryPeak,
                'execution_time_ms' => $executionTimeMs,
            ],
            'extensions' => $extensions,
            'databases' => $dbStatus,
        ];

        // Return JSON if requested via Accept header or query param (?format=json or ?json)
        if ($request->wantsJson() || $request->query('format') === 'json' || $request->has('json')) {
            return response()->json($data, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        // Otherwise return interactive HTML view
        return response()->view('diagnosis', [
            'data' => $data,
            'executionTimeMs' => $executionTimeMs,
        ]);
    }
}
