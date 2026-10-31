<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsageLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'ai_model_id',
        'task_role',
        'provider_slug',
        'model_id',
        'prompt_tokens',
        'completion_tokens',
        'latency_ms',
        'used_byok',
        'cache_hit',
        'status',
    ];

    protected $casts = [
        'prompt_tokens' => 'integer',
        'completion_tokens' => 'integer',
        'latency_ms' => 'integer',
        'used_byok' => 'boolean',
        'cache_hit' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function aiModel(): BelongsTo
    {
        return $this->belongsTo(AiModel::class);
    }
}
