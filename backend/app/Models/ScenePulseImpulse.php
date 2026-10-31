<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScenePulseImpulse extends Model
{
    use HasFactory;

    protected $fillable = [
        'video_item_id',
        'time_bucket',
        'timestamp_formatted',
        'frame_hash',
        'scene_headline',
        'visual_signals',
        'audio_10s_signals',
        'qloo_resolved_entities',
        'qloo_taste_tags',
        'ad_offers',
        'metrics',
        'ai_provider',
        'qloo_live_verified',
        'latency_ms',
        'hit_count',
    ];

    protected function casts(): array
    {
        return [
            'time_bucket' => 'integer',
            'visual_signals' => 'array',
            'audio_10s_signals' => 'array',
            'qloo_resolved_entities' => 'array',
            'qloo_taste_tags' => 'array',
            'ad_offers' => 'array',
            'metrics' => 'array',
            'qloo_live_verified' => 'boolean',
            'latency_ms' => 'integer',
            'hit_count' => 'integer',
        ];
    }

    public function videoItem(): BelongsTo
    {
        return $this->belongsTo(VideoItem::class, 'video_item_id');
    }
}
