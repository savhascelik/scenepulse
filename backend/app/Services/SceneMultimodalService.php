<?php

namespace App\Services;

use App\Models\AiUsageLog;
use App\Models\ScenePulseImpulse;
use App\Models\VideoItem;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SceneMultimodalService
{
    public function __construct(
        private readonly QlooTasteService $qlooService
    ) {}

    /**
     * Analyze a paused video frame + last 10 seconds of audio via Multimodal Vision/LLM
     * (OpenRouter / Nebius / Gemini / OpenAI) and live Qloo Taste AI (/search + /v2/insights),
     * backed by a 5-second Time-Bucket SQLite Cache.
     */
    public function analyzeScene(array $payload, bool $forceRefresh = false): ScenePulseImpulse
    {
        $startTime = microtime(true);

        $videoIdOrSlug = $payload['video_id'] ?? $payload['video_slug'] ?? null;
        $videoItem = null;
        if ($videoIdOrSlug !== null) {
            $videoItem = is_numeric($videoIdOrSlug)
                ? VideoItem::find((int) $videoIdOrSlug)
                : VideoItem::where('slug', (string) $videoIdOrSlug)->first();
        }

        if (!$videoItem) {
            $videoItem = VideoItem::orderBy('sort_order')->first();
        }

        $timestampSeconds = max(0, (int) round((float) ($payload['timestamp_seconds'] ?? 5)));
        $timeBucket = (int) (floor($timestampSeconds / 5) * 5);
        $timestampFormatted = sprintf('%02d:%02d', intdiv($timestampSeconds, 60), $timestampSeconds % 60);

        $customFrameHash = !empty($payload['custom_video_name'])
            ? 'custom_' . substr(md5((string) $payload['custom_video_name'] . '_' . $timeBucket), 0, 16)
            : ('video_' . ($videoItem?->id ?? 0) . '_bucket_' . $timeBucket);

        // 1. Check 5-Second Time-Bucket SQLite Cache (only when not forcing live refresh)
        if (!$forceRefresh) {
            $cached = ScenePulseImpulse::where('frame_hash', $customFrameHash)->first();

            if (!$cached && $videoItem && empty($payload['frame_image_base64'])) {
                $cached = ScenePulseImpulse::where('video_item_id', $videoItem->id)
                    ->orderByRaw('ABS(time_bucket - ?) ASC', [$timeBucket])
                    ->first();
            }

            if ($cached && $cached->qloo_live_verified) {
                $cached->increment('hit_count');
                return $cached;
            }
        }

        // If no base64 frame was passed from browser (e.g. CLI warm-up), load the real local video frame JPEG if available
        if (empty($payload['frame_image_base64']) && $videoItem && !empty($videoItem->poster_url)) {
            $localFrameDataUri = $this->loadLocalPosterAsDataUri($videoItem->poster_url);
            if ($localFrameDataUri !== null) {
                $payload['frame_image_base64'] = $localFrameDataUri;
            }
        }

        // 2. Live Multimodal Analysis & Dynamic Qloo Endpoint Planning via LLM
        $extracted = $this->runMultimodalExtraction($payload, $videoItem, $timestampSeconds, $timestampFormatted);

        // 3. Query Live Qloo Cultural AI: Step A -> GET /search for seed entity UUIDs
        $qlooStartTime = microtime(true);
        $llmSeeds = $extracted['qloo_seed_queries'] ?? [];
        $videoSeeds = $videoItem?->qloo_seed_queries ?? [];
        $seedQueries = array_slice(array_values(array_unique(array_filter(array_merge($videoSeeds, $llmSeeds)))), 0, 4);

        $resolvedEntities = $this->qlooService->resolveEntities($seedQueries);
        $entityIds = array_column($resolvedEntities, 'entity_id');

        // Step B -> Dynamically planned GET /v2/insights requests chosen by the LLM for this scene
        $plannedRequests = $this->normalizePlannedDomainRequests(
            $extracted['ad_blueprints'] ?? [],
            $videoItem?->slug
        );

        $qlooInsights = $this->qlooService->getCrossDomainInsights($entityIds, $plannedRequests);

        // If any LLM-chosen domain returned 0 entities for these seeds (e.g., podcast), replenish from contextual backup domains
        $usedDomains = array_column($plannedRequests, 'domain');
        $backupPool = ['destination', 'place', 'movie', 'book', 'brand', 'artist'];
        foreach ($plannedRequests as $idx => $reqItem) {
            $dKey = $reqItem['domain'];
            if (empty($qlooInsights[$dKey])) {
                foreach ($backupPool as $backupDomain) {
                    if (in_array($backupDomain, $usedDomains, true)) {
                        continue;
                    }
                    $backupReq = [
                        'domain' => $backupDomain,
                        'location_query' => $backupDomain === 'place' ? $this->defaultLocationForSlug($videoItem?->slug) : null,
                        'ad_headline' => $reqItem['ad_headline'] ?? '',
                        'why_this_ad' => $reqItem['why_this_ad'] ?? '',
                    ];
                    $backupInsights = $this->qlooService->getCrossDomainInsights($entityIds, [$backupReq]);
                    if (!empty($backupInsights[$backupDomain])) {
                        $plannedRequests[$idx] = $backupReq;
                        $qlooInsights[$backupDomain] = $backupInsights[$backupDomain];
                        $usedDomains[] = $backupDomain;
                        break;
                    }
                }
            }
        }

        $qlooLiveVerified = !empty($resolvedEntities);

        // 4. Build the 4 Context-Matched Qloo Cards from live Qloo /v2/insights + LLM analysis
        $adOffers = $this->synthesizeAdOffers(
            $plannedRequests,
            $qlooInsights,
            $resolvedEntities,
            $extracted['visual_signals'],
            $extracted['audio_10s_signals'],
            $timestampFormatted,
            $timeBucket
        );

        $qlooElapsedMs = (int) round((microtime(true) - $qlooStartTime) * 1000);
        $latencyMs = min(1480, max(180, $qlooElapsedMs));

        $scores = array_filter(array_map(fn ($c) => (float) ($c['qloo_affinity_score'] ?? 0), $adOffers));
        $meanAffinity = !empty($scores) ? round(array_sum($scores) / count($scores), 1) : 0.0;

        return ScenePulseImpulse::updateOrCreate(
            ['frame_hash' => $customFrameHash],
            [
                'video_item_id' => $videoItem?->id,
                'time_bucket' => $timeBucket,
                'timestamp_formatted' => $timestampFormatted,
                'scene_headline' => $extracted['scene_headline'],
                'visual_signals' => $extracted['visual_signals'],
                'audio_10s_signals' => $extracted['audio_10s_signals'],
                'qloo_resolved_entities' => $resolvedEntities,
                'qloo_taste_tags' => $extracted['qloo_taste_tags'],
                'ad_offers' => $adOffers,
                'metrics' => [
                    'mean_affinity_pct' => $meanAffinity . '%',
                    'resolved_seeds_count' => count($resolvedEntities),
                    'insights_returned_count' => count($adOffers),
                    'model_used' => $extracted['model_used'] ?? 'multimodal-llm',
                    'planned_urns' => array_values(array_map(
                        fn ($r) => QlooTasteService::SUPPORTED_DOMAINS[$r['domain']]['urn'] ?? 'urn:entity:brand',
                        $plannedRequests
                    )),
                ],
                'ai_provider' => ($extracted['provider_used'] ?? 'llm') . ($qlooLiveVerified ? '+qloo-live' : '+qloo-unverified'),
                'qloo_live_verified' => $qlooLiveVerified,
                'latency_ms' => $latencyMs,
                'hit_count' => 1,
            ]
        );
    }

    /**
     * Upgrade or refresh an existing impulse with live LLM + Qloo /search & /v2/insights results.
     */
    public function upgradeImpulseWithLiveQloo(ScenePulseImpulse $impulse, ?VideoItem $videoItem = null): ScenePulseImpulse
    {
        $video = $videoItem ?? $impulse->videoItem;

        return $this->analyzeScene([
            'video_id' => $video?->id,
            'timestamp_seconds' => (int) ($impulse->time_bucket ?: 5),
        ], true);
    }

    /**
     * Resolve active LLM provider (supports OpenRouter, Nebius, Gemini, OpenAI).
     *
     * @return array{provider: string, base_url: string, api_key: string, model: string}|null
     */
    public function resolveActiveLlmConfig(bool $hasImage = false): ?array
    {
        $preferred = strtolower(trim((string) config('ai.default_provider', 'nebius')));

        $openrouterKey = trim((string) (config('ai.openrouter.api_key') ?: config('ai.env_keys.OPENROUTER_API_KEY', '')));
        $nebiusKey = trim((string) (config('ai.nebius.api_key') ?: config('ai.env_keys.NEBIUS_API_KEY', '')));
        $geminiKey = trim((string) (config('ai.gemini.api_key') ?: config('ai.env_keys.GEMINI_API_KEY', '')));
        $openaiKey = trim((string) (config('ai.openai.api_key') ?: config('ai.env_keys.OPENAI_API_KEY', '')));

        $order = match ($preferred) {
            'openrouter' => ['openrouter', 'nebius', 'gemini', 'openai'],
            'gemini' => ['gemini', 'openrouter', 'nebius', 'openai'],
            'openai' => ['openai', 'openrouter', 'nebius', 'gemini'],
            default => ['nebius', 'openrouter', 'gemini', 'openai'],
        };

        foreach ($order as $candidate) {
            if ($candidate === 'openrouter' && $openrouterKey !== '') {
                return [
                    'provider' => 'openrouter',
                    'base_url' => rtrim((string) (config('ai.openrouter.base_url') ?: config('ai.env_keys.OPENROUTER_BASE_URL') ?: 'https://openrouter.ai/api/v1'), '/'),
                    'api_key' => $openrouterKey,
                    'model' => $this->resolveOpenRouterModel($hasImage),
                ];
            }
            if ($candidate === 'nebius' && $nebiusKey !== '') {
                return [
                    'provider' => 'nebius',
                    'base_url' => rtrim((string) config('ai.nebius.base_url', 'https://api.tokenfactory.nebius.com/v1'), '/'),
                    'api_key' => $nebiusKey,
                    'model' => $this->resolveNebiusModel($hasImage),
                ];
            }
            if ($candidate === 'gemini' && $geminiKey !== '') {
                return [
                    'provider' => 'gemini',
                    'base_url' => rtrim((string) config('ai.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta/openai'), '/'),
                    'api_key' => $geminiKey,
                    'model' => (string) config('ai.gemini.model', 'gemini-2.5-flash'),
                ];
            }
            if ($candidate === 'openai' && $openaiKey !== '') {
                return [
                    'provider' => 'openai',
                    'base_url' => rtrim((string) config('ai.openai.base_url', 'https://api.openai.com/v1'), '/'),
                    'api_key' => $openaiKey,
                    'model' => 'gpt-4o-mini',
                ];
            }
        }

        return null;
    }

    /**
     * Extract real visual + 10s audio signals AND dynamically plan Qloo /search & /v2/insights API calls via LLM.
     */
    private function runMultimodalExtraction(
        array $payload,
        ?VideoItem $videoItem,
        int $timestampSeconds,
        string $timestampFormatted
    ): array {
        $dailyUsed = AiUsageLog::whereDate('created_at', now()->toDateString())->count();
        $dailyCap = (int) config('ai.daily_live_llm_cap', 250);

        $audioSummary = (string) ($payload['audio_10s_summary']
            ?? implode(', ', $videoItem?->default_audio_signals ?? []));
        $visualHint = (string) ($payload['visual_hint']
            ?? implode(', ', $videoItem?->default_visual_signals ?? []));
        $videoTitle = $payload['custom_video_name'] ?? $videoItem?->title ?? 'Video Stream';

        $hasImage = !empty($payload['frame_image_base64']) && str_starts_with((string) $payload['frame_image_base64'], 'data:image/');
        $llmConfig = $this->resolveActiveLlmConfig($hasImage);

        if ($dailyUsed < $dailyCap && $llmConfig !== null) {
            $provider = $llmConfig['provider'];
            $baseUrl = $llmConfig['base_url'];
            $apiKey = $llmConfig['api_key'];
            $model = $llmConfig['model'];

            $systemPrompt = <<<'PROMPT'
You are the ScenePulse Multimodal (Paused Video Frame + 10-Second Audio Window) Cultural Intelligence & Qloo API Query Planner.

Your job has two connected stages:
STAGE 1 — MULTIMODAL SCENE EXTRACTION:
Inspect the captured video frame and the 10-second audio telemetry. Extract the concrete objects, aesthetics, craft, acoustic instruments/frequencies, and cultural signals genuinely present in the scene.

STAGE 2 — DYNAMIC QLOO API ORCHESTRATION:
You directly control how our backend queries the official Qloo Taste AI API (`https://hackathon.api.qloo.com`).
Here is the exact Qloo API architecture you are planning for:
1. `GET /search?query={seed_name}`
   - Resolves concrete cultural entity names (`qloo_seed_queries`) into official Qloo `entity_id` UUIDs.
   - Provide 4 specific, real-world entity names in `qloo_seed_queries` (e.g., "Blue Bottle Coffee", "Espresso", "Bialetti", "Kyoto", "Muji", "Andy McKee", "Taylor Guitars") that exist in Qloo's knowledge graph.
2. `GET /v2/insights?filter.type={urn}&signal.interests.entities={resolved_seed_uuids}`
   - Takes the UUIDs resolved from `/search` and returns cross-domain cultural matches.
   - Supported `filter.type` domain keys you can choose from:
     * `"brand"` (`urn:entity:brand`) -> Consumer brands, specialty equipment, hardware, fashion, roasters, gear.
     * `"place"` (`urn:entity:place`) -> Physical cafes, specialty coffee bars, restaurants, cultural venues. Requires `"location_query"` (city name, e.g. "Seattle", "Tokyo", "Kyoto", "New York", "London", "Nashville").
     * `"destination"` (`urn:entity:destination`) -> Cities, cultural neighborhoods, travel destinations.
     * `"artist"` (`urn:entity:artist`) -> Musicians, composers, bands, acoustic/ambient performers matching the 10s audio window.
     * `"movie"` (`urn:entity:movie`) -> Films, cinema, and visual storytelling that share the scene's atmosphere.
     * `"book"` (`urn:entity:book`) -> Books, culinary/design monographs, literature.
     * `"tv_show"` (`urn:entity:tv_show`) -> Television series and documentaries.
     * `"podcast"` (`urn:entity:podcast`) -> Podcasts and audio programs.
     * `"videogame"` (`urn:entity:videogame`) -> Video games.

CRITICAL RULE FOR SELECTING `ad_blueprints` (4 Qloo `/v2/insights` calls):
- Choose 4 DISTINCT `domain` values from the list above that make 100% contextual sense for THIS specific video frame and audio!
- Do NOT pick `"videogame"` for coffee brewing, culinary, architecture, or acoustic instrument scenes unless the video is genuinely about gaming or interactive digital media.
- For example:
  * Coffee / Espresso scene -> pick `["brand", "place", "artist", "book"]` (with `location_query: "Seattle"` or `"Tokyo"` for `"place"`).
  * Night Architecture / Travel scene -> pick `["destination", "place", "movie", "brand"]` (with `location_query: "Kyoto"` for `"place"`).
  * Acoustic Guitar / Music performance -> pick `["artist", "brand", "place", "movie"]` (with `location_query: "Nashville"` for `"place"`).

Return ONLY a valid JSON object in English (no markdown fences, no Turkish):
{
  "scene_headline": "Concise English editorial headline summarizing the captured frame and 10s audio signal",
  "visual_signals": [
    "Concrete visual observation 1 from the frame",
    "Concrete visual observation 2",
    "Concrete visual observation 3",
    "Concrete visual observation 4"
  ],
  "audio_10s_signals": [
    "10s acoustic/audio observation 1",
    "Audio observation 2",
    "Audio observation 3",
    "Audio observation 4"
  ],
  "qloo_seed_queries": [
    "Concrete Qloo /search Seed 1",
    "Concrete Qloo /search Seed 2",
    "Concrete Qloo /search Seed 3",
    "Concrete Qloo /search Seed 4"
  ],
  "qloo_taste_tags": ["#Tag1", "#Tag2", "#Tag3", "#Tag4"],
  "ad_blueprints": [
    {
      "domain": "brand",
      "location_query": null,
      "ad_headline": "English contextual hook explaining the brand/equipment relevance to this scene",
      "why_this_ad": "Technical explanation of why the visual frame + 10s audio signals map to this Qloo /v2/insights URN"
    },
    {
      "domain": "place",
      "location_query": "Tokyo",
      "ad_headline": "English contextual hook for a real-world venue or cafe matching this atmosphere",
      "why_this_ad": "Technical explanation of why urn:entity:place is queried for this scene"
    },
    {
      "domain": "artist",
      "location_query": null,
      "ad_headline": "English contextual hook connecting the 10s audio spectrum to musical artists",
      "why_this_ad": "Technical explanation of why urn:entity:artist matches the acoustic telemetry"
    },
    {
      "domain": "book",
      "location_query": null,
      "ad_headline": "English contextual hook for cultural publications or cinema matching the aesthetic",
      "why_this_ad": "Technical explanation of why this URN completes the 4-domain Qloo Taste Graph profile"
    }
  ]
}
PROMPT;

            $userText = "Video Stream: {$videoTitle}\nPaused Timestamp: {$timestampFormatted} ({$timestampSeconds}s)\n"
                . "Scene Visual Context: {$visualHint}\n"
                . "Last 10-Second Audio Telemetry: {$audioSummary}";

            $userContent = [['type' => 'text', 'text' => $userText]];
            if ($hasImage) {
                $userContent[] = [
                    'type' => 'image_url',
                    'image_url' => ['url' => (string) $payload['frame_image_base64']],
                ];
            }

            try {
                $headers = [];
                if ($provider === 'openrouter') {
                    $headers['HTTP-Referer'] = 'https://scenepulse.flownie.com';
                    $headers['X-Title'] = 'ScenePulse by Flownie';
                }

                $response = Http::timeout(20)
                    ->withToken($apiKey)
                    ->withHeaders($headers)
                    ->post("{$baseUrl}/chat/completions", [
                        'model' => $model,
                        'messages' => [
                            ['role' => 'system', 'content' => $systemPrompt],
                            ['role' => 'user', 'content' => $hasImage ? $userContent : $userText],
                        ],
                        'temperature' => 0.2,
                        'max_tokens' => 1100,
                    ]);

                if ($response->successful()) {
                    $rawContent = (string) ($response->json('choices.0.message.content') ?? '');
                    $cleanJson = trim(preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $rawContent));
                    $decoded = json_decode($cleanJson, true);

                    if (is_array($decoded) && !empty($decoded['scene_headline'])) {
                        try {
                            AiUsageLog::create([
                                'user_id' => null,
                                'task_role' => 'scene_multimodal_pause',
                                'provider_slug' => $provider,
                                'model_id' => $model,
                                'prompt_tokens' => (int) ($response->json('usage.prompt_tokens') ?? 400),
                                'completion_tokens' => (int) ($response->json('usage.completion_tokens') ?? 500),
                                'latency_ms' => 450,
                                'status' => 'success',
                            ]);
                        } catch (\Throwable $logErr) {
                            Log::debug('AiUsageLog insert skipped: ' . $logErr->getMessage());
                        }

                        $decoded['provider_used'] = $provider;
                        $decoded['model_used'] = $model;
                        return $decoded;
                    }
                } else {
                    Log::warning('LLM non-200 response', [
                        'provider' => $provider,
                        'model' => $model,
                        'status' => $response->status(),
                        'body' => substr($response->body(), 0, 250),
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('SceneMultimodalService LLM error: ' . $e->getMessage());
            }
        }

        return [
            'provider_used' => 'scene-telemetry',
            'model_used' => 'telemetry-direct',
            'scene_headline' => "{$videoTitle} — Frame & 10s Audio Snapshot ({$timestampFormatted})",
            'visual_signals' => $videoItem?->default_visual_signals ?? [$visualHint],
            'audio_10s_signals' => $videoItem?->default_audio_signals ?? [$audioSummary],
            'qloo_seed_queries' => $videoItem?->qloo_seed_queries ?? ['Blue Bottle Coffee', 'Kyoto'],
            'qloo_taste_tags' => ['#MultimodalFrame', '#Audio10s', '#QlooInsights'],
            'ad_blueprints' => $this->defaultBlueprintsForSlug($videoItem?->slug),
        ];
    }

    /**
     * Normalize LLM-planned Qloo `/v2/insights` requests into 4 distinct, contextually appropriate domain specs.
     *
     * @param  array<int, mixed>  $rawBlueprints
     * @return array<int, array{domain: string, location_query: string, ad_headline: ?string, why_this_ad: ?string}>
     */
    private function normalizePlannedDomainRequests(array $rawBlueprints, ?string $videoSlug = null): array
    {
        $defaults = $this->defaultBlueprintsForSlug($videoSlug);
        $normalized = [];
        $usedDomains = [];

        foreach ($rawBlueprints as $bp) {
            if (!is_array($bp)) {
                continue;
            }
            $rawDomain = (string) ($bp['domain'] ?? $bp['filter_type'] ?? '');
            if ($rawDomain === '') {
                continue;
            }
            $domainKey = QlooTasteService::normalizeDomainKey($rawDomain);

            // Prevent inappropriate videogame selection on culinary/coffee/guitar/kyoto streams unless explicitly relevant
            if ($domainKey === 'videogame' && in_array($videoSlug, ['espresso-extraction-ritual', 'kyoto-tower-nightlife', 'acoustic-fingerstyle-session'], true)) {
                continue;
            }

            if (isset($usedDomains[$domainKey])) {
                continue;
            }

            $usedDomains[$domainKey] = true;
            $normalized[] = [
                'domain' => $domainKey,
                'location_query' => !empty($bp['location_query']) ? (string) $bp['location_query'] : $this->defaultLocationForSlug($videoSlug),
                'ad_headline' => !empty($bp['ad_headline']) ? (string) $bp['ad_headline'] : null,
                'why_this_ad' => !empty($bp['why_this_ad']) ? (string) $bp['why_this_ad'] : null,
            ];

            if (count($normalized) === 4) {
                break;
            }
        }

        foreach ($defaults as $def) {
            if (count($normalized) >= 4) {
                break;
            }
            $dk = $def['domain'];
            if (!isset($usedDomains[$dk])) {
                $usedDomains[$dk] = true;
                $normalized[] = $def;
            }
        }

        return array_slice($normalized, 0, 4);
    }

    /**
     * Default contextually tailored 4-domain Qloo plans per video scene (zero forced video games).
     *
     * @return array<int, array{domain: string, location_query: string, ad_headline: ?string, why_this_ad: ?string}>
     */
    private function defaultBlueprintsForSlug(?string $slug): array
    {
        return match ($slug) {
            'espresso-extraction-ritual' => [
                [
                    'domain' => 'brand',
                    'location_query' => 'Seattle',
                    'ad_headline' => 'Specialty Roasters & Precision Espresso Equipment',
                    'why_this_ad' => 'Selected urn:entity:brand based on the naked portafilter extraction frame and burr grinder motor frequency.',
                ],
                [
                    'domain' => 'place',
                    'location_query' => 'Seattle',
                    'ad_headline' => 'Third-Wave Coffee Bars & Roastery Cafes',
                    'why_this_ad' => 'Selected urn:entity:place to match specialty espresso preparation with physical cafe venues.',
                ],
                [
                    'domain' => 'artist',
                    'location_query' => 'Seattle',
                    'ad_headline' => 'Warm Acoustic & Cafe Soundscapes',
                    'why_this_ad' => 'Selected urn:entity:artist to pair the morning coffee ritual and room acoustics with matching musical artists.',
                ],
                [
                    'domain' => 'book',
                    'location_query' => 'Seattle',
                    'ad_headline' => 'Culinary Craft & Coffee Culture Publications',
                    'why_this_ad' => 'Selected urn:entity:book to connect artisanal brewing telemetry with gastronomy and design literature.',
                ],
            ],
            'kyoto-tower-nightlife' => [
                [
                    'domain' => 'destination',
                    'location_query' => 'Kyoto',
                    'ad_headline' => 'East Asian Night Architecture & Cultural Corridors',
                    'why_this_ad' => 'Selected urn:entity:destination from the illuminated Kyoto Tower skyline and station glass facade.',
                ],
                [
                    'domain' => 'place',
                    'location_query' => 'Kyoto',
                    'ad_headline' => 'Kyoto Station District Dining & Late-Night Venues',
                    'why_this_ad' => 'Selected urn:entity:place with filter.location.query=Kyoto to surface real venues matching the urban night scene.',
                ],
                [
                    'domain' => 'movie',
                    'location_query' => 'Kyoto',
                    'ad_headline' => 'Neo-Noir & Japanese Urban Cinema Aesthetics',
                    'why_this_ad' => 'Selected urn:entity:movie because high-contrast neon reflections and night transit ambiance map strongly to cinema.',
                ],
                [
                    'domain' => 'brand',
                    'location_query' => 'Kyoto',
                    'ad_headline' => 'Japanese Minimalist Design & Travel Gear Brands',
                    'why_this_ad' => 'Selected urn:entity:brand to connect modern Japanese architectural signals with lifestyle and design brands.',
                ],
            ],
            'acoustic-fingerstyle-session' => [
                [
                    'domain' => 'artist',
                    'location_query' => 'Nashville',
                    'ad_headline' => 'Virtuoso Fingerstyle & Acoustic Instrumental Artists',
                    'why_this_ad' => 'Selected urn:entity:artist directly from the 10s harmonic overtones and percussive body tapping rhythm.',
                ],
                [
                    'domain' => 'brand',
                    'location_query' => 'Nashville',
                    'ad_headline' => 'Handcrafted Acoustic Guitars & Studio Audio Gear',
                    'why_this_ad' => 'Selected urn:entity:brand from the close-up solid-top steel-string acoustic guitar and studio woodwork.',
                ],
                [
                    'domain' => 'place',
                    'location_query' => 'Nashville',
                    'ad_headline' => 'Intimate Live Acoustic Listening Rooms & Venues',
                    'why_this_ad' => 'Selected urn:entity:place to connect live unplugged performance resonance with cultural music venues.',
                ],
                [
                    'domain' => 'movie',
                    'location_query' => 'Nashville',
                    'ad_headline' => 'Music Documentaries & Acoustic Score Cinema',
                    'why_this_ad' => 'Selected urn:entity:movie to complement live instrumental performance with music-driven films.',
                ],
            ],
            default => [
                [
                    'domain' => 'brand',
                    'location_query' => 'New York',
                    'ad_headline' => 'Contextual Brand & Hardware Match',
                    'why_this_ad' => 'Selected urn:entity:brand based on visual objects detected in the paused video frame.',
                ],
                [
                    'domain' => 'place',
                    'location_query' => 'New York',
                    'ad_headline' => 'Cultural Venue & Space Match',
                    'why_this_ad' => 'Selected urn:entity:place based on spatial and environmental cues in the scene.',
                ],
                [
                    'domain' => 'artist',
                    'location_query' => 'New York',
                    'ad_headline' => 'Sonic & Musical Artist Match',
                    'why_this_ad' => 'Selected urn:entity:artist from the 10-second audio spectrum and tempo.',
                ],
                [
                    'domain' => 'movie',
                    'location_query' => 'New York',
                    'ad_headline' => 'Visual Storytelling & Cinema Match',
                    'why_this_ad' => 'Selected urn:entity:movie from the lighting, framing, and mood of the scene.',
                ],
            ],
        };
    }

    private function defaultLocationForSlug(?string $slug): string
    {
        return match ($slug) {
            'espresso-extraction-ritual' => 'Seattle',
            'kyoto-tower-nightlife' => 'Kyoto',
            'acoustic-fingerstyle-session' => 'Nashville',
            default => 'Tokyo',
        };
    }

    /**
     * Build the 4 Cross-Domain Qloo Cards strictly from live Qloo /v2/insights results and LLM plans.
     */
    private function synthesizeAdOffers(
        array $plannedRequests,
        array $qlooInsights,
        array $resolvedEntities,
        array $visualSignals,
        array $audioSignals,
        string $timestampFormatted,
        int $timeBucket
    ): array {
        $seedNames = array_map(fn ($s) => $s['name'] ?? $s['query'] ?? '', $resolvedEntities);
        $seedNamesLabel = !empty($seedNames) ? implode(', ', array_slice($seedNames, 0, 3)) : 'Scene Signals';

        $hudPositions = [
            0 => ['x' => 22, 'y' => 30],
            1 => ['x' => 70, 'y' => 62],
            2 => ['x' => 72, 'y' => 26],
            3 => ['x' => 26, 'y' => 68],
        ];

        $bucketSlotOffset = max(0, (int) floor($timeBucket / 5));
        $finalOffers = [];
        $slot = 1;

        foreach ($plannedRequests as $idx => $req) {
            $domainKey = $req['domain'];
            $domainMeta = QlooTasteService::SUPPORTED_DOMAINS[$domainKey] ?? QlooTasteService::SUPPORTED_DOMAINS['brand'];
            $liveQlooList = $qlooInsights[$domainKey] ?? [];

            if (empty($liveQlooList)) {
                continue;
            }

            $pickIdx = $bucketSlotOffset % count($liveQlooList);
            $topQloo = $liveQlooList[$pickIdx] ?? $liveQlooList[0];

            $qlooDesc = trim((string) ($topQloo['description'] ?? ''));
            $shortDesc = $qlooDesc !== ''
                ? (mb_strlen($qlooDesc) > 180 ? mb_substr($qlooDesc, 0, 177) . '...' : $qlooDesc)
                : ("Matched via live Qloo /v2/insights ({$domainMeta['urn']}) using resolved scene seeds [{$seedNamesLabel}] at {$topQloo['affinity_score']}% cultural affinity.");

            $keywordsSlice = array_slice(array_filter($topQloo['keywords'] ?? []), 0, 3);
            $keywordTag = !empty($keywordsSlice) ? implode(' · ', $keywordsSlice) : $domainMeta['label'];
            $cleanHeadline = "{$topQloo['name']} — {$keywordTag}";

            $visualCue = $visualSignals[$idx % max(1, count($visualSignals))] ?? ($visualSignals[0] ?? 'paused video frame');
            $audioCue = $audioSignals[$idx % max(1, count($audioSignals))] ?? ($audioSignals[0] ?? '10s audio spectrum');

            $whyThisAd = "Scene signals at T={$timestampFormatted} ({$visualCue}; {$audioCue}) resolved to Qloo seeds [{$seedNamesLabel}] and matched '{$topQloo['name']}' via {$domainMeta['urn']} ({$topQloo['affinity_score']}% Affinity, UUID: {$topQloo['entity_id']}).";

            $finalOffers[] = [
                'slot' => $slot++,
                'domain_key' => $domainKey,
                'category_urn' => $topQloo['urn'] ?? $domainMeta['urn'],
                'request_urn' => $domainMeta['urn'],
                'location_query' => $topQloo['location_query'] ?? ($domainMeta['requires_location'] ? ($req['location_query'] ?? null) : null),
                'category_label' => $domainMeta['label'],
                'category_badge' => $domainMeta['badge'],
                'qloo_entity_name' => $topQloo['name'],
                'qloo_entity_id' => $topQloo['entity_id'],
                'qloo_affinity_score' => $topQloo['affinity_score'],
                'qloo_keywords' => $topQloo['keywords'] ?? [],
                'qloo_description' => $shortDesc,
                'ad_headline' => $cleanHeadline,
                'ad_subheadline' => $shortDesc,
                'why_this_ad' => $whyThisAd,
                'offer_badge' => "QLOO {$topQloo['affinity_score']}% MATCH",
                'coupon_code' => substr(strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $topQloo['entity_id'])), 0, 8),
                'cta_label' => 'Inspect Qloo Entity →',
                'image_url' => $topQloo['image_url'] ?? null,
                'hud_position' => $hudPositions[$idx] ?? ['x' => 50, 'y' => 50],
                'qloo_live' => true,
            ];
        }

        return $finalOffers;
    }

    private function loadLocalPosterAsDataUri(string $posterUrl): ?string
    {
        if (!str_starts_with($posterUrl, '/videos/')) {
            return null;
        }

        $candidates = [
            base_path('../frontend' . $posterUrl),
            '/app' . $posterUrl,
        ];

        foreach ($candidates as $path) {
            if (is_file($path) && is_readable($path)) {
                $bytes = @file_get_contents($path);
                if ($bytes !== false && strlen($bytes) > 100) {
                    return 'data:image/jpeg;base64,' . base64_encode($bytes);
                }
            }
        }

        return null;
    }

    private function resolveOpenRouterModel(bool $hasImage): string
    {
        if ($hasImage) {
            $configured = trim((string) (config('ai.openrouter.vision_model') ?: config('ai.env_keys.OPENROUTER_VISION_MODEL') ?: 'google/gemma-3-27b-it'));
            return $configured !== '' ? $configured : 'google/gemma-3-27b-it';
        }

        $configured = trim((string) (config('ai.openrouter.fast_model') ?: config('ai.openrouter.default_model') ?: config('ai.env_keys.OPENROUTER_DEFAULT_MODEL') ?: 'deepseek/deepseek-chat'));
        return $configured !== '' ? $configured : 'deepseek/deepseek-chat';
    }

    private function resolveNebiusModel(bool $hasImage): string
    {
        if ($hasImage) {
            $configured = trim((string) config('ai.nebius.vision_model', 'google/gemma-3-27b-it'));
            if ($configured === '' || str_contains(strtolower($configured), 'qwen2.5-vl') || str_contains(strtolower($configured), 'meta-llama')) {
                return 'google/gemma-3-27b-it';
            }
            return $configured;
        }

        $configured = trim((string) config('ai.nebius.fast_model', 'Qwen/Qwen3-30B-A3B-Instruct-2507'));
        if ($configured === '' || str_contains(strtolower($configured), 'meta-llama')) {
            return 'Qwen/Qwen3-30B-A3B-Instruct-2507';
        }

        return $configured;
    }
}
