<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class QlooTasteService
{
    /**
     * Full catalog of Qloo Taste AI /v2/insights entity URNs available to the LLM planner.
     */
    public const SUPPORTED_DOMAINS = [
        'brand' => [
            'urn' => 'urn:entity:brand',
            'label' => 'BRAND & EQUIPMENT',
            'badge' => 'BRAND',
            'requires_location' => false,
        ],
        'place' => [
            'urn' => 'urn:entity:place',
            'label' => 'VENUE & DINING',
            'badge' => 'PLACE',
            'requires_location' => true,
        ],
        'destination' => [
            'urn' => 'urn:entity:destination',
            'label' => 'CULTURAL DESTINATION',
            'badge' => 'DESTINATION',
            'requires_location' => false,
        ],
        'artist' => [
            'urn' => 'urn:entity:artist',
            'label' => 'MUSIC & SOUND ARTIST',
            'badge' => 'ARTIST',
            'requires_location' => false,
        ],
        'movie' => [
            'urn' => 'urn:entity:movie',
            'label' => 'FILM & CINEMA',
            'badge' => 'FILM',
            'requires_location' => false,
        ],
        'book' => [
            'urn' => 'urn:entity:book',
            'label' => 'BOOK & LITERATURE',
            'badge' => 'BOOK',
            'requires_location' => false,
        ],
        'tv_show' => [
            'urn' => 'urn:entity:tv_show',
            'label' => 'SERIES & TELEVISION',
            'badge' => 'TV SHOW',
            'requires_location' => false,
        ],
        'podcast' => [
            'urn' => 'urn:entity:podcast',
            'label' => 'PODCAST & AUDIO',
            'badge' => 'PODCAST',
            'requires_location' => false,
        ],
        'videogame' => [
            'urn' => 'urn:entity:videogame',
            'label' => 'VIDEO GAME',
            'badge' => 'GAME',
            'requires_location' => false,
        ],
    ];

    /**
     * Normalize any domain key or full Qloo URN string (e.g. "urn:entity:brand" or "brand")
     * into a canonical key from SUPPORTED_DOMAINS.
     */
    public static function normalizeDomainKey(string $domainOrUrn): string
    {
        $clean = strtolower(trim($domainOrUrn));
        $clean = str_replace('urn:entity:', '', $clean);

        $aliases = [
            'brands' => 'brand',
            'places' => 'place',
            'cafe' => 'place',
            'restaurant' => 'place',
            'food_and_drink' => 'place',
            'destinations' => 'destination',
            'city' => 'destination',
            'artists' => 'artist',
            'music' => 'artist',
            'movies' => 'movie',
            'film' => 'movie',
            'books' => 'book',
            'tv' => 'tv_show',
            'show' => 'tv_show',
            'podcasts' => 'podcast',
            'video_game' => 'videogame',
            'videogames' => 'videogame',
            'game' => 'videogame',
        ];

        $mapped = $aliases[$clean] ?? $clean;

        return array_key_exists($mapped, self::SUPPORTED_DOMAINS) ? $mapped : 'brand';
    }

    public function isConfigured(): bool
    {
        return !empty($this->getApiKey());
    }

    public function getBaseUrl(): string
    {
        $url = trim((string) (
            config('ai.qloo.server_url')
            ?: config('ai.env_keys.QLOO_SERVER_URL')
            ?: config('services.qloo.server_url')
            ?: config('ai.qloo.base_url')
            ?: 'https://hackathon.api.qloo.com'
        ));

        return rtrim($url, '/');
    }

    private function getApiKey(): string
    {
        return trim((string) (config('ai.qloo.api_key') ?: config('ai.env_keys.QLOO_API_KEY', '')));
    }

    /**
     * Resolve natural-language cultural seed terms into official Qloo entity IDs via GET /search.
     * Strictly uses live Qloo API without any fake fallback IDs.
     *
     * @param  array<int, string>  $queries
     * @return array<int, array{query: string, entity_id: string, name: string, type: string, popularity: float, image_url: ?string, is_live: bool}>
     */
    public function resolveEntities(array $queries): array
    {
        $resolved = [];
        $apiKey = $this->getApiKey();
        $baseUrl = $this->getBaseUrl();
        $ttlSeconds = max(3600, (int) config('ai.qloo.cache_ttl_hours', 168) * 3600);

        if ($apiKey === '') {
            return [];
        }

        foreach (array_slice(array_unique(array_filter($queries)), 0, 5) as $query) {
            $cleanQuery = trim((string) $query);
            if ($cleanQuery === '') {
                continue;
            }

            $cacheKey = 'qloo_live_search_v4:' . md5(strtolower($cleanQuery));

            $entity = Cache::remember($cacheKey, $ttlSeconds, function () use ($cleanQuery, $apiKey, $baseUrl) {
                try {
                    $response = Http::timeout(10)
                        ->withHeaders([
                            'X-Api-Key' => $apiKey,
                            'Accept' => 'application/json',
                        ])
                        ->get("{$baseUrl}/search", [
                            'query' => $cleanQuery,
                            'take' => 3,
                        ]);

                    if ($response->successful()) {
                        $json = $response->json();
                        $results = $json['results'] ?? $json['entities'] ?? [];
                        if (!empty($results) && is_array($results)) {
                            $first = $results[0];
                            $entityId = $first['entity_id'] ?? $first['id'] ?? null;
                            if ($entityId) {
                                return [
                                    'query' => $cleanQuery,
                                    'entity_id' => (string) $entityId,
                                    'name' => (string) ($first['name'] ?? $cleanQuery),
                                    'type' => (string) ($first['types'][0] ?? $first['subtype'] ?? $first['type'] ?? 'urn:entity'),
                                    'popularity' => round((float) ($first['popularity'] ?? 0.90) * 100, 1),
                                    'image_url' => $first['properties']['image']['url'] ?? null,
                                    'is_live' => true,
                                ];
                            }
                        }
                    } else {
                        Log::warning('Qloo /search non-200 response', [
                            'query' => $cleanQuery,
                            'status' => $response->status(),
                        ]);
                    }
                } catch (\Throwable $e) {
                    Log::warning('Qloo /search exception: ' . $e->getMessage(), ['query' => $cleanQuery]);
                }

                return null;
            });

            if (is_array($entity) && !empty($entity['entity_id'])) {
                $resolved[] = $entity;
            }
        }

        return $resolved;
    }

    /**
     * Fetch real cross-domain cultural taste recommendations via GET /v2/insights.
     * Supports dynamic LLM-selected domain plans and location queries for urn:entity:place.
     *
     * @param  array<int, string>  $seedEntityIds
     * @param  array<int, string|array<string, mixed>>  $domainRequests
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function getCrossDomainInsights(array $seedEntityIds, array $domainRequests = ['brand', 'place', 'artist', 'book']): array
    {
        $apiKey = $this->getApiKey();
        $baseUrl = $this->getBaseUrl();
        $ttlSeconds = max(3600, (int) config('ai.qloo.cache_ttl_hours', 168) * 3600);

        $liveEntityIds = array_values(array_filter($seedEntityIds, fn ($id) => !empty($id) && !str_starts_with((string) $id, 'qloo-')));
        $insightsByDomain = [];

        if ($apiKey === '' || empty($liveEntityIds)) {
            foreach ($domainRequests as $req) {
                $dk = is_array($req)
                    ? self::normalizeDomainKey((string) ($req['domain'] ?? $req['filter_type'] ?? 'brand'))
                    : self::normalizeDomainKey((string) $req);
                $insightsByDomain[$dk] = [];
            }
            return $insightsByDomain;
        }

        foreach ($domainRequests as $req) {
            $domainKey = is_array($req)
                ? self::normalizeDomainKey((string) ($req['domain'] ?? $req['filter_type'] ?? 'brand'))
                : self::normalizeDomainKey((string) $req);

            $locationQuery = is_array($req) && !empty($req['location_query'])
                ? trim((string) $req['location_query'])
                : 'Tokyo';

            $domainMeta = self::SUPPORTED_DOMAINS[$domainKey] ?? self::SUPPORTED_DOMAINS['brand'];
            $urn = $domainMeta['urn'];
            $cacheKey = 'qloo_live_insights_v7:' . md5($urn . ':' . $locationQuery . ':' . implode(',', $liveEntityIds));

            $entities = Cache::remember($cacheKey, $ttlSeconds, function () use ($baseUrl, $apiKey, $urn, $locationQuery, $liveEntityIds) {
                $candidateSignalSets = [implode(',', array_slice($liveEntityIds, 0, 5))];
                if (!empty($liveEntityIds[0]) && $candidateSignalSets[0] !== $liveEntityIds[0]) {
                    $candidateSignalSets[] = $liveEntityIds[0];
                }

                $locationFallbacks = $urn === 'urn:entity:place'
                    ? array_slice(array_values(array_unique(array_filter([$locationQuery, 'New York', 'Tokyo']))), 0, 2)
                    : [''];

                foreach ($locationFallbacks as $loc) {
                    foreach ($candidateSignalSets as $signalEntitiesParam) {
                        try {
                            $params = [
                                'filter.type' => $urn,
                                'signal.interests.entities' => $signalEntitiesParam,
                                'take' => 5,
                            ];
                            if ($urn === 'urn:entity:place' && $loc !== '') {
                                $params['filter.location.query'] = $loc;
                            }

                            $response = Http::timeout(8)
                                ->withHeaders([
                                    'X-Api-Key' => $apiKey,
                                    'Accept' => 'application/json',
                                ])
                                ->get("{$baseUrl}/v2/insights", $params);

                            if ($response->successful()) {
                                $json = $response->json();
                                $rawEntities = $json['results']['entities'] ?? $json['results'] ?? $json['entities'] ?? [];
                                $parsed = [];
                                if (is_array($rawEntities)) {
                                    foreach (array_slice($rawEntities, 0, 5) as $idx => $item) {
                                        if (!is_array($item) || empty($item['name'])) {
                                            continue;
                                        }
                                        $affinityRaw = $item['query']['affinity'] ?? $item['popularity'] ?? null;
                                        $affinityPct = $affinityRaw !== null
                                            ? ($affinityRaw <= 1.0 ? round((float) $affinityRaw * 100, 1) : round((float) $affinityRaw, 1))
                                            : round(95.0 - ($idx * 1.5), 1);

                                        $keywords = [];
                                        $rawKw = $item['properties']['keywords'] ?? [];
                                        if (is_array($rawKw)) {
                                            foreach (array_slice($rawKw, 0, 6) as $kwEntry) {
                                                if (is_string($kwEntry) && trim($kwEntry) !== '') {
                                                    $keywords[] = trim($kwEntry);
                                                } elseif (is_array($kwEntry) && !empty($kwEntry['name']) && is_string($kwEntry['name'])) {
                                                    $keywords[] = trim($kwEntry['name']);
                                                }
                                            }
                                        }
                                        if (empty($keywords) && !empty($item['tags']) && is_array($item['tags'])) {
                                            foreach (array_slice($item['tags'], 0, 4) as $tagObj) {
                                                if (is_array($tagObj) && !empty($tagObj['name']) && is_string($tagObj['name'])) {
                                                    $keywords[] = trim($tagObj['name']);
                                                }
                                            }
                                        }

                                        $descCandidates = [
                                            $item['properties']['short_description'] ?? null,
                                            $item['properties']['description'] ?? null,
                                            $item['properties']['tagline'] ?? null,
                                            $item['properties']['address'] ?? null,
                                            $item['disambiguation'] ?? null,
                                        ];
                                        $description = '';
                                        foreach ($descCandidates as $cand) {
                                            if (is_string($cand) && trim($cand) !== '') {
                                                $description = trim($cand);
                                                break;
                                            } elseif (is_array($cand)) {
                                                $nested = $cand['value'] ?? $cand['text'] ?? $cand['formatted'] ?? null;
                                                if (is_string($nested) && trim($nested) !== '') {
                                                    $description = trim($nested);
                                                    break;
                                                }
                                            }
                                        }

                                        $subtypeRaw = $item['subtype'] ?? $urn;
                                        $subtypeStr = is_string($subtypeRaw) ? $subtypeRaw : $urn;
                                        $imgRaw = $item['properties']['image']['url'] ?? null;

                                        $parsed[] = [
                                            'entity_id' => (string) ($item['entity_id'] ?? $item['id'] ?? ''),
                                            'name' => is_string($item['name']) ? $item['name'] : 'Qloo Entity',
                                            'urn' => $subtypeStr,
                                            'request_urn' => $urn,
                                            'location_query' => $urn === 'urn:entity:place' ? $loc : null,
                                            'affinity_score' => $affinityPct,
                                            'description' => $description,
                                            'keywords' => array_slice(array_values(array_unique($keywords)), 0, 4),
                                            'image_url' => is_string($imgRaw) ? $imgRaw : null,
                                            'is_live' => true,
                                        ];
                                    }
                                }
                                if (!empty($parsed)) {
                                    return $parsed;
                                }
                            }
                        } catch (\Throwable $e) {
                            Log::warning('Qloo /v2/insights exception: ' . $e->getMessage(), ['urn' => $urn]);
                        }
                    }
                }

                return [];
            });

            $insightsByDomain[$domainKey] = $entities;
        }

        return $insightsByDomain;
    }

    /**
     * Direct interactive Qloo Taste Explorer via live /search + /v2/insights.
     */
    public function exploreLive(string $query, string $domainOrUrn = 'brand', ?string $locationQuery = null): array
    {
        $domainKey = self::normalizeDomainKey($domainOrUrn);
        $domainMeta = self::SUPPORTED_DOMAINS[$domainKey] ?? self::SUPPORTED_DOMAINS['brand'];

        $resolved = $this->resolveEntities([$query]);
        $entityIds = array_column($resolved, 'entity_id');

        $reqSpec = [
            'domain' => $domainKey,
            'filter_type' => $domainMeta['urn'],
            'location_query' => $locationQuery ?: 'Tokyo',
        ];

        $insights = $this->getCrossDomainInsights($entityIds, [$reqSpec]);
        $domainInsights = $insights[$domainKey] ?? [];

        return [
            'query' => $query,
            'domain' => $domainKey,
            'target_urn' => $domainMeta['urn'],
            'qloo_configured' => $this->isConfigured(),
            'qloo_server_url' => $this->getBaseUrl(),
            'resolved_seeds' => $resolved,
            'insights' => $domainInsights,
        ];
    }
}
