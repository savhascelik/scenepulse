<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiCreditUsage extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'ai_model_id',
        'action_type',
        'provider_slug',
        'model_id',
        'input_tokens',
        'output_tokens',
        'total_tokens',
        'raw_cost_usd',
        'credits_charged',
        'latency_ms',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'total_tokens' => 'integer',
            'raw_cost_usd' => 'decimal:6',
            'credits_charged' => 'decimal:4',
            'latency_ms' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function aiModel(): BelongsTo
    {
        return $this->belongsTo(AiModel::class, 'ai_model_id');
    }
}
