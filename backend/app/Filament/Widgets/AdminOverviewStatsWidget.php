<?php

namespace App\Filament\Widgets;

use App\Models\AiCreditBalance;
use App\Models\AiCreditUsage;
use App\Models\AiModel;
use App\Models\ScenePulseImpulse;
use App\Models\VideoItem;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AdminOverviewStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $totalVideos = VideoItem::count();
        $totalImpulses = ScenePulseImpulse::count();
        $totalCacheHits = (int) ScenePulseImpulse::sum('hit_count');
        $activeModels = AiModel::where('is_active', true)->count();
        $totalAiCalls = AiCreditUsage::count();

        return [
            Stat::make('Aktif Video Akışları', (string) $totalVideos)
                ->description('Holografik vitrindeki küratörlü videolar'),
            Stat::make('Qloo Sahne Darbeleri (Time-Buckets)', (string) $totalImpulses)
                ->description("{$totalCacheHits} kez sıfır-maliyetle önbellekten sunuldu"),
            Stat::make('Aktif Multimodal & LLM Modelleri', (string) $activeModels)
                ->description("Toplam {$totalAiCalls} canlı AI çağrısı kaydedildi"),
            Stat::make('Gizlilik & GDPR Skoru', '%100 Zero-Cookie')
                ->description('Qloo Taste Graph + Görüntü/10sn Ses bağlamsal eşleşme'),
        ];
    }
}
