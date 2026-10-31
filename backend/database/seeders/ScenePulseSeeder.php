<?php

namespace Database\Seeders;

use App\Models\ScenePulseImpulse;
use App\Models\VideoItem;
use App\Services\SceneMultimodalService;
use Illuminate\Database\Seeder;

class ScenePulseSeeder extends Seeder
{
    public function run(): void
    {
        $validSlugs = [
            'espresso-extraction-ritual',
            'kyoto-tower-nightlife',
            'acoustic-fingerstyle-session',
        ];

        $obsoleteVideoIds = VideoItem::whereNotIn('slug', $validSlugs)->pluck('id');
        if ($obsoleteVideoIds->isNotEmpty()) {
            ScenePulseImpulse::whereIn('video_item_id', $obsoleteVideoIds)->delete();
            VideoItem::whereIn('id', $obsoleteVideoIds)->delete();
        }

        $videos = [
            [
                'slug' => 'espresso-extraction-ritual',
                'title' => 'Scene 01 — Specialty Espresso Grinding, Tamping & Bottomless Extraction',
                'subtitle' => '16s · Bottomless Portafilter Crema Flow + Burr Grinder & 9-Bar Pump Telemetry',
                'creator_name' => 'Stream 01 · Gastronomy & Precision Hardware Signal',
                'creator_handle' => 'espresso-ritual.webm',
                'creator_followers' => '480p VP9 + Opus',
                'published_label' => 'Real Video Frame + 10s Audio',
                'card_index_label' => 'SCENE 01 / 03',
                'category_pills' => ['GASTRONOMY', 'ESPRESSO HARDWARE', 'ACOUSTIC TELEMETRY', 'QLOO /V2/INSIGHTS'],
                'video_url' => '/videos/espresso-ritual.webm',
                'poster_url' => '/videos/espresso-ritual.jpg',
                'duration_seconds' => 16,
                'duration_badge' => '0:16',
                'views_label' => '16s',
                'likes_label' => 'VP9/Opus',
                'bookmarks_label' => '4 Domains',
                'shares_label' => 'Live Qloo',
                'default_visual_signals' => [
                    'Freshly roasted whole-bean coffee weighed inside a single-dose grinder hopper',
                    'WDT needle distribution and calibrated stainless-steel tamper preparation',
                    'Bottomless portafilter pulling a tiger-striped espresso crema extraction',
                    'Brushed stainless espresso grouphead and thick-walled ceramic demitasse cup',
                ],
                'default_audio_signals' => [
                    'Last 10s: High-RPM conical burr coffee grinder motor frequency',
                    'Metallic portafilter lock-in and stainless-steel tamper mechanical click',
                    '9-bar vibratory espresso pump pressure profile and liquid extraction stream',
                    'Warm specialty coffee bar room acoustics',
                ],
                'qloo_seed_queries' => ['Lavazza', 'Blue Bottle Coffee', 'Nespresso', 'Starbucks Reserve'],
                'editorial_summary' => 'When paused, the exact HTML5 <canvas> frame and 10-second audio window are sent to the multimodal LLM alongside the full Qloo API specification. The LLM selects contextually relevant Qloo /v2/insights URNs (Brand, Place, Artist, Book — never unrelated categories like video games) and resolves real Qloo entities.',
                'sort_order' => 1,
                'is_featured' => true,
            ],
            [
                'slug' => 'kyoto-tower-nightlife',
                'title' => 'Scene 02 — Kyoto Station & Illuminated Kyoto Tower Night Architecture',
                'subtitle' => '28s · High-Contrast Urban Neon, Station Glass Facade & Japanese Night Ambience',
                'creator_name' => 'Stream 02 · Urban Architecture & Cultural Travel Signal',
                'creator_handle' => 'kyoto-night.webm',
                'creator_followers' => '480p VP9 + Opus',
                'published_label' => 'Real Video Frame + 10s Audio',
                'card_index_label' => 'SCENE 02 / 03',
                'category_pills' => ['KYOTO ARCHITECTURE', 'NIGHT SKYLINE', 'URBAN ACOUSTICS', 'QLOO /V2/INSIGHTS'],
                'video_url' => '/videos/kyoto-night.webm',
                'poster_url' => '/videos/kyoto-night.jpg',
                'duration_seconds' => 28,
                'duration_badge' => '0:28',
                'views_label' => '28s',
                'likes_label' => 'VP9/Opus',
                'bookmarks_label' => '4 Domains',
                'shares_label' => 'Live Qloo',
                'default_visual_signals' => [
                    'Illuminated red-and-white Kyoto Tower spire against the night sky',
                    'Modern glass-and-steel architectural reflections across Kyoto Station',
                    'High-contrast urban street lighting and nighttime transit corridor',
                    'Wide-angle cityscape framing with evening pedestrian and traffic motion',
                ],
                'default_audio_signals' => [
                    'Last 10s: Open-air central station plaza urban night ambience in Japan',
                    'Low-frequency evening transit hum and gentle outdoor wind profile',
                    'Distant pedestrian crossing chimes and architectural acoustic reverberation',
                    'Calm nighttime metropolitan soundstage',
                ],
                'qloo_seed_queries' => ['Kyoto', 'Tokyo', 'Muji', 'Studio Ghibli'],
                'editorial_summary' => 'The night skyline, architectural reflections, and ambient station audio in Kyoto are analyzed by the multimodal LLM, which dynamically selects Destination, Place (filtered to Kyoto), Film, and Brand endpoints on Qloo /v2/insights.',
                'sort_order' => 2,
                'is_featured' => true,
            ],
            [
                'slug' => 'acoustic-fingerstyle-session',
                'title' => 'Scene 03 — Percussive Acoustic Fingerstyle Guitar Performance',
                'subtitle' => '16s · Steel-String Harmonic Overtones, Fretboard Tapping & Body Percussion',
                'creator_name' => 'Stream 03 · Musical Instrument & Acoustic Rhythm Signal',
                'creator_handle' => 'acoustic-guitar.webm',
                'creator_followers' => '480p VP9 + Opus',
                'published_label' => 'Real Video Frame + 10s Audio',
                'card_index_label' => 'SCENE 03 / 03',
                'category_pills' => ['ACOUSTIC GUITAR', 'FINGERSTYLE RHYTHM', 'LIVE SESSION', 'QLOO /V2/INSIGHTS'],
                'video_url' => '/videos/acoustic-guitar.webm',
                'poster_url' => '/videos/acoustic-guitar.jpg',
                'duration_seconds' => 16,
                'duration_badge' => '0:16',
                'views_label' => '16s',
                'likes_label' => 'VP9/Opus',
                'bookmarks_label' => '4 Domains',
                'shares_label' => 'Live Qloo',
                'default_visual_signals' => [
                    'Close-up framing of a solid-top steel-string acoustic guitar',
                    'Articulate two-handed fingerstyle chord voicings and fretboard tapping',
                    'Rhythmic right-hand percussive strikes on the wooden soundboard',
                    'Warm natural studio lighting highlighting wood grain and phosphor-bronze strings',
                ],
                'default_audio_signals' => [
                    'Last 10s: Bright steel-string acoustic guitar harmonic overtones and arpeggios',
                    'Syncopated spruce-top body percussion bass pulse',
                    'High-transient live unplugged instrumental recording',
                    'Warm studio room acoustic resonance (108 BPM)',
                ],
                'qloo_seed_queries' => ['Andy McKee', 'Fender', 'Taylor Guitars', 'Acoustic Guitar'],
                'editorial_summary' => 'From the close-up luthiery framing and 10-second harmonic/percussive audio window, the LLM queries Qloo /search and dynamically selects Artist, Brand, Place, and Film endpoints on Qloo /v2/insights.',
                'sort_order' => 3,
                'is_featured' => true,
            ],
        ];

        /** @var SceneMultimodalService $multimodalService */
        $multimodalService = app(SceneMultimodalService::class);

        foreach ($videos as $videoData) {
            $videoItem = VideoItem::updateOrCreate(
                ['slug' => $videoData['slug']],
                $videoData
            );

            foreach ([5, 15] as $bucketSec) {
                $existing = ScenePulseImpulse::where('video_item_id', $videoItem->id)
                    ->where('time_bucket', $bucketSec)
                    ->where('qloo_live_verified', true)
                    ->exists();

                if (!$existing) {
                    $multimodalService->analyzeScene([
                        'video_id' => $videoItem->id,
                        'timestamp_seconds' => $bucketSec,
                    ], true);
                }
            }
        }
    }
}
