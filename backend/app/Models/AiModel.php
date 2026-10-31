<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiModel extends Model
{
    use HasFactory;

    public const ROLE_ARCHITECT = 'architect';
    public const ROLE_FAST_DIAGNOSE = 'fast_diagnose';
    public const ROLE_DATASHEET_SYNTH = 'datasheet_synth';

    protected $fillable = [
        'provider_slug',
        'display_name',
        'base_url',
        'api_key_env',
        'model_id',
        'model_family',
        'task_role',
        'input_cost_per_1m',
        'output_cost_per_1m',
        'token_multiplier',
        'input_multiplier',
        'output_multiplier',
        'pricing_tiers',
        'supports_vision',
        'supports_function_calling',
        'supports_code_mode',
        'supports_thinking',
        'max_input_tokens',
        'max_output_tokens',
        'default_temperature',
        'context_budget_max_tokens',
        'default_extra_payload',
        'is_default',
        'is_active',
        'sort_order',
        'description',
    ];

    /**
     * Never expose provider API key env names, internal base URLs, or raw cost structures on public endpoints.
     *
     * @var list<string>
     */
    protected $hidden = [
        'api_key_env',
        'base_url',
        'input_cost_per_1m',
        'output_cost_per_1m',
        'pricing_tiers',
        'default_extra_payload',
    ];

    protected function casts(): array
    {
        return [
            'input_cost_per_1m' => 'decimal:6',
            'output_cost_per_1m' => 'decimal:6',
            'token_multiplier' => 'decimal:2',
            'input_multiplier' => 'decimal:2',
            'output_multiplier' => 'decimal:2',
            'pricing_tiers' => 'array',
            'supports_vision' => 'boolean',
            'supports_function_calling' => 'boolean',
            'supports_code_mode' => 'boolean',
            'supports_thinking' => 'boolean',
            'max_input_tokens' => 'integer',
            'max_output_tokens' => 'integer',
            'default_temperature' => 'decimal:2',
            'context_budget_max_tokens' => 'integer',
            'default_extra_payload' => 'array',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function usages(): HasMany
    {
        return $this->hasMany(AiCreditUsage::class, 'ai_model_id');
    }

    public static function getDefaultForTask(string $taskRole): ?self
    {
        return self::query()
            ->where('is_active', true)
            ->where(function ($q) use ($taskRole) {
                $q->where('task_role', $taskRole)->orWhere('task_role', 'all');
            })
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first()
            ?? self::query()
                ->where('is_active', true)
                ->orderByDesc('is_default')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->first();
    }

    public function getEffectiveInputMultiplier(): float
    {
        return (float) ($this->input_multiplier ?? $this->token_multiplier ?? 2.0);
    }

    public function getEffectiveOutputMultiplier(): float
    {
        return (float) ($this->output_multiplier ?? $this->token_multiplier ?? 2.0);
    }

    /**
     * Resolve effective input/output costs per 1M tokens (supports tiered pricing).
     *
     * @return array{input_cost: float, output_cost: float}
     */
    public function getEffectiveCosts(int $inputTokens): array
    {
        $tiers = $this->pricing_tiers;
        if (! empty($tiers) && is_array($tiers)) {
            usort($tiers, fn ($a, $b) => ($a['max_input_tokens'] ?? PHP_INT_MAX) <=> ($b['max_input_tokens'] ?? PHP_INT_MAX));
            foreach ($tiers as $tier) {
                $maxTokens = $tier['max_input_tokens'] ?? null;
                if ($maxTokens === null || $inputTokens <= $maxTokens) {
                    return [
                        'input_cost' => (float) ($tier['input_cost_per_1m'] ?? $this->input_cost_per_1m),
                        'output_cost' => (float) ($tier['output_cost_per_1m'] ?? $this->output_cost_per_1m),
                    ];
                }
            }
            $last = end($tiers);

            return [
                'input_cost' => (float) ($last['input_cost_per_1m'] ?? $this->input_cost_per_1m),
                'output_cost' => (float) ($last['output_cost_per_1m'] ?? $this->output_cost_per_1m),
            ];
        }

        return [
            'input_cost' => (float) ($this->input_cost_per_1m ?? 0.15),
            'output_cost' => (float) ($this->output_cost_per_1m ?? 0.60),
        ];
    }

    public function calculateCost(int $inputTokens, int $outputTokens): float
    {
        $costs = $this->getEffectiveCosts($inputTokens);
        $inputCost = ($inputTokens / 1_000_000) * $costs['input_cost'];
        $outputCost = ($outputTokens / 1_000_000) * $costs['output_cost'];

        return round($inputCost + $outputCost, 6);
    }

    /**
     * Calculate AI Credits to charge for a given token usage.
     * 1 USD base cost * multiplier = 1000 AI Credits, with a minimum floor of 0.25 credits per call
     * so every real AI synthesis/agent turn deducts a visible, deterministic credit amount.
     */
    public function calculateChargedCredits(int $inputTokens, int $outputTokens): float
    {
        $costs = $this->getEffectiveCosts($inputTokens);
        $inputCost = ($inputTokens / 1_000_000) * $costs['input_cost'] * $this->getEffectiveInputMultiplier();
        $outputCost = ($outputTokens / 1_000_000) * $costs['output_cost'] * $this->getEffectiveOutputMultiplier();
        $computed = round(($inputCost + $outputCost) * 1000, 4);

        return max(0.2500, $computed);
    }
}
