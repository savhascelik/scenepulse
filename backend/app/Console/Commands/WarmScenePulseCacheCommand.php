<?php

namespace App\Console\Commands;

use App\Models\ScenePulseImpulse;
use App\Models\VideoItem;
use App\Services\QlooTasteService;
use App\Services\SceneMultimodalService;
use Illuminate\Console\Command;

class WarmScenePulseCacheCommand extends Command
{
    protected $signature = 'scenepulse:warm {--force : Force refresh all video buckets via live Multimodal LLM & Qloo Taste AI}';

    protected $description = 'Warm 5-second Time-Bucket SQLite Cache with live Multimodal LLM + Qloo Taste Graph (/search & /v2/insights) entities.';

    public function handle(SceneMultimodalService $multimodalService, QlooTasteService $qlooService): int
    {
        $this->info('ScenePulse by Flownie — Live Algorithm Cache Warmer');
        $this->line('Qloo Server URL: ' . $qlooService->getBaseUrl());
        $this->line('Qloo Key Configured: ' . ($qlooService->isConfigured() ? 'YES (Live Qloo Mode)' : 'NO'));

        $videos = VideoItem::orderBy('sort_order')->get();
        $force = (bool) $this->option('force');

        foreach ($videos as $video) {
            $this->info("Processing video [{$video->slug}] ({$video->duration_seconds}s)...");
            $buckets = $video->duration_seconds >= 25 ? [5, 15, 25] : [5, 10, 15];

            foreach ($buckets as $sec) {
                $existing = ScenePulseImpulse::where('frame_hash', "video_{$video->id}_bucket_{$sec}")->first();
                if (!$existing || $force || !$existing->qloo_live_verified || count($existing->ad_offers ?? []) < 4) {
                    $impulse = $multimodalService->analyzeScene([
                        'video_id' => $video->id,
                        'timestamp_seconds' => $sec,
                    ], true);
                } else {
                    $impulse = $existing;
                }
                $this->line(" -> Bucket T={$impulse->timestamp_formatted} (bucket={$impulse->time_bucket}) ready | Provider: {$impulse->ai_provider} | Cards: " . count($impulse->ad_offers ?? []));
            }
        }

        $this->info('Done! All real video scene time-buckets are populated in English via live LLM + Qloo.');
        return self::SUCCESS;
    }
}
