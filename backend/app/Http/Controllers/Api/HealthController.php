<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ScenePulseImpulse;
use App\Models\VideoItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'status' => 'ok',
            'service' => 'scenepulse-flownie-api',
            'timestamp' => now()->toIso8601String(),
            'services' => [],
        ];

        $httpStatus = 200;

        try {
            DB::connection()->getPdo();
            $checks['services']['database'] = 'ok';
            $checks['services']['videos_seeded'] = VideoItem::count();
            $checks['services']['cached_time_buckets'] = ScenePulseImpulse::count();
        } catch (\Throwable $e) {
            $checks['services']['database'] = 'error';
            $checks['status'] = 'degraded';
            $httpStatus = 500;
        }

        try {
            $probeKey = 'health_check_probe';
            Cache::put($probeKey, 'ok', 10);
            $checks['services']['cache'] = Cache::get($probeKey) === 'ok' ? 'ok' : 'error';
            if ($checks['services']['cache'] !== 'ok') {
                $checks['status'] = 'degraded';
                $httpStatus = 500;
            }
        } catch (\Throwable $e) {
            $checks['services']['cache'] = 'error';
            $checks['status'] = 'degraded';
            $httpStatus = 500;
        }

        $storageWritable = is_writable(storage_path('app')) && is_writable(storage_path('logs'));
        $checks['services']['storage'] = $storageWritable ? 'ok' : 'error';
        if (!$storageWritable) {
            $checks['status'] = 'degraded';
            $httpStatus = 500;
        }

        $checks['services']['ai_and_qloo_config'] = [
            'default_provider' => config('ai.default_provider', 'nebius'),
            'qloo_base_url' => config('ai.qloo.base_url', 'https://hackathon.api.qloo.com'),
            'qloo_key_configured' => !empty(config('ai.qloo.api_key') ?: config('ai.env_keys.QLOO_API_KEY')),
            'nebius_key_configured' => !empty(config('ai.nebius.api_key') ?: config('ai.env_keys.NEBIUS_API_KEY')),
            'gemini_key_configured' => !empty(config('ai.gemini.api_key') ?: config('ai.env_keys.GEMINI_API_KEY')),
            'daily_live_llm_cap' => (int) config('ai.daily_live_llm_cap', 250),
        ];

        return response()->json($checks, $httpStatus);
    }
}
