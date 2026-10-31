<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VideoItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'title',
        'subtitle',
        'creator_name',
        'creator_handle',
        'creator_followers',
        'published_label',
        'card_index_label',
        'category_pills',
        'video_url',
        'poster_url',
        'duration_seconds',
        'duration_badge',
        'views_label',
        'likes_label',
        'bookmarks_label',
        'shares_label',
        'default_visual_signals',
        'default_audio_signals',
        'qloo_seed_queries',
        'editorial_summary',
        'sort_order',
        'is_featured',
    ];

    protected function casts(): array
    {
        return [
            'category_pills' => 'array',
            'default_visual_signals' => 'array',
            'default_audio_signals' => 'array',
            'qloo_seed_queries' => 'array',
            'duration_seconds' => 'integer',
            'sort_order' => 'integer',
            'is_featured' => 'boolean',
        ];
    }

    public function impulses(): HasMany
    {
        return $this->hasMany(ScenePulseImpulse::class, 'video_item_id');
    }
}
