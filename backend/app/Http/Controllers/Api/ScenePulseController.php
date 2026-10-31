<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ScenePulseImpulse;
use App\Models\VideoItem;
use App\Services\QlooTasteService;
use App\Services\SceneMultimodalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScenePulseController extends Controller
{
    public function __construct(
        private readonly SceneMultimodalService $multimodalService,
        private readonly QlooTasteService $qlooService
    ) {}

    /**
     * GET /api/v1/scenes/catalog
     * Public showcase catalog of aligned short video scenes + real-time Qloo impulses.
     */
    public function catalog(): JsonResponse
    {
        $videos = VideoItem::with(['impulses' => function ($q) {
            $q->orderBy('time_bucket');
        }])
            ->orderBy('sort_order')
            ->get();

        $recentImpulses = ScenePulseImpulse::with('videoItem:id,slug,title,duration_badge,creator_handle')
            ->orderByDesc('updated_at')
            ->limit(12)
            ->get();

        $activeLlm = $this->multimodalService->resolveActiveLlmConfig(false);

        return response()->json([
            'status' => 'ok',
            'engine' => 'ScenePulse by Flownie — Multimodal Video Frame & 10s Audio to Qloo Taste AI Engine',
            'qloo_status' => [
                'base_url' => $this->qlooService->getBaseUrl(),
                'api_key_configured' => $this->qlooService->isConfigured(),
                'supported_domains' => QlooTasteService::SUPPORTED_DOMAINS,
            ],
            'llm_status' => [
                'active_provider' => $activeLlm['provider'] ?? 'telemetry',
                'active_model' => $activeLlm['model'] ?? 'telemetry-direct',
                'supported_providers' => ['openrouter', 'nebius', 'gemini', 'openai'],
            ],
            'stats' => [
                'total_videos' => $videos->count(),
                'total_cached_buckets' => ScenePulseImpulse::count(),
                'total_cache_hits' => (int) ScenePulseImpulse::sum('hit_count'),
            ],
            'videos' => $videos,
            'live_ad_stream' => $recentImpulses,
        ]);
    }

    /**
     * GET /api/v1/scenes/videos/{idOrSlug}
     * Returns detail view data for a specific video card + active 5s time-bucket impulse.
     */
    public function showVideo(Request $request, string $idOrSlug): JsonResponse
    {
        $video = is_numeric($idOrSlug)
            ? VideoItem::with('impulses')->find((int) $idOrSlug)
            : VideoItem::with('impulses')->where('slug', $idOrSlug)->first();

        if (!$video) {
            return response()->json(['message' => 'Video stream not found.'], 404);
        }

        $timestampSeconds = (int) round((float) $request->query('t', 5));
        $activeImpulse = $this->multimodalService->analyzeScene([
            'video_id' => $video->id,
            'timestamp_seconds' => $timestampSeconds,
        ]);

        return response()->json([
            'status' => 'ok',
            'video' => $video,
            'active_impulse' => $activeImpulse,
            'time_buckets' => $video->impulses()->orderBy('time_bucket')->get(),
        ]);
    }

    /**
     * POST /api/v1/scenes/analyze
     * Real-time pause & play analysis: accepts paused video frame + 10s audio context,
     * checks 5s time-bucket SQLite cache first, or runs live Multimodal LLM + Qloo API.
     */
    public function analyzePause(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'video_id' => 'nullable',
            'video_slug' => 'nullable|string|max:120',
            'timestamp_seconds' => 'nullable|numeric|min:0|max:7200',
            'frame_image_base64' => 'nullable|string',
            'audio_10s_summary' => 'nullable|string|max:1000',
            'visual_hint' => 'nullable|string|max:1000',
            'custom_video_name' => 'nullable|string|max:180',
            'force_refresh' => 'nullable|boolean',
        ]);

        $forceRefresh = (bool) ($validated['force_refresh'] ?? false);
        $impulse = $this->multimodalService->analyzeScene($validated, $forceRefresh);

        return response()->json([
            'status' => 'ok',
            'cached_shield_active' => $impulse->hit_count > 1,
            'hit_count' => $impulse->hit_count,
            'qloo_live_verified' => $impulse->qloo_live_verified,
            'impulse' => $impulse,
        ]);
    }

    /**
     * GET /api/v1/qloo/explore
     * Direct Qloo Taste Graph explorer endpoint (0 LLM tokens used).
     */
    public function exploreQloo(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', 'Espresso'));
        $domain = trim((string) $request->query('domain', 'brand'));
        $location = trim((string) $request->query('location', 'Tokyo'));

        $data = $this->qlooService->exploreLive($query, $domain, $location);

        return response()->json([
            'status' => 'ok',
            'data' => $data,
        ]);
    }
}
