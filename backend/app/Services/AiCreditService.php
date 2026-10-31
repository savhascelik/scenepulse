<?php

namespace App\Services;

use App\Models\AiCreditBalance;
use App\Models\AiCreditTransaction;
use App\Models\AiCreditUsage;
use App\Models\AiModel;
use App\Models\RoleLimit;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AiCreditService
{
    public function __construct(
        private readonly LimitService $limitService
    ) {}

    public function getBalance(User $user): AiCreditBalance
    {
        return $user->ensureInitialCreditGrant();
    }

    public function getDailyTokensUsed(int $userId): int
    {
        return (int) AiCreditUsage::query()
            ->where('user_id', $userId)
            ->where('created_at', '>=', now()->startOfDay())
            ->sum('total_tokens');
    }

    public function getMonthlyTokensUsed(int $userId): int
    {
        return (int) AiCreditUsage::query()
            ->where('user_id', $userId)
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('total_tokens');
    }

    public function getBalanceSummary(User $user): array
    {
        $balance = $this->getBalance($user);

        $dailyTokenLimit = $this->limitService->getUserLimit($user, RoleLimit::RESOURCE_AI_USAGE, 'daily_token_limit');
        $monthlyTokenLimit = $this->limitService->getUserLimit($user, RoleLimit::RESOURCE_AI_USAGE, 'monthly_token_limit');
        $maxTokensPerRequest = $this->limitService->getUserLimit($user, RoleLimit::RESOURCE_AI_USAGE, 'max_tokens_per_request');

        return [
            'promotional_credits' => round((float) $balance->promotional_credits, 2),
            'purchased_credits' => round((float) $balance->purchased_credits, 2),
            'available_credits' => round($balance->available_credits, 2),
            'total_earned_promotional' => round((float) $balance->total_earned_promotional, 2),
            'total_purchased' => round((float) $balance->total_purchased, 2),
            'total_spent' => round((float) $balance->total_spent, 2),
            'daily_tokens_used' => $this->getDailyTokensUsed($user->id),
            'daily_token_limit' => $dailyTokenLimit,
            'monthly_tokens_used' => $this->getMonthlyTokensUsed($user->id),
            'monthly_token_limit' => $monthlyTokenLimit,
            'max_tokens_per_request' => $maxTokensPerRequest,
        ];
    }

    /**
     * Check whether the user has enough AI credits and is within role token limits.
     *
     * @return array{allowed: bool, reason?: string, message?: string, available_credits: float, required_credits: float}
     */
    public function canMakeRequest(User $user, float $minCreditsRequired = 0.25): array
    {
        $balance = $this->getBalance($user);
        $available = $balance->available_credits;

        if ($available < $minCreditsRequired) {
            return [
                'allowed' => false,
                'reason' => 'insufficient_credits',
                'message' => 'AI krediniz tükendi. Devre sentezi ve AI ajanını kullanmaya devam etmek için kredi paketi yükleyin.',
                'available_credits' => round($available, 2),
                'required_credits' => $minCreditsRequired,
            ];
        }

        $dailyLimit = $this->limitService->getUserLimit($user, RoleLimit::RESOURCE_AI_USAGE, 'daily_token_limit');
        if ($dailyLimit !== -1) {
            $dailyUsed = $this->getDailyTokensUsed($user->id);
            if ($dailyUsed >= $dailyLimit) {
                return [
                    'allowed' => false,
                    'reason' => 'daily_token_limit_exceeded',
                    'message' => "Günlük AI token limitinize ({$dailyLimit}) ulaştınız.",
                    'available_credits' => round($available, 2),
                    'required_credits' => $minCreditsRequired,
                ];
            }
        }

        $monthlyLimit = $this->limitService->getUserLimit($user, RoleLimit::RESOURCE_AI_USAGE, 'monthly_token_limit');
        if ($monthlyLimit !== -1) {
            $monthlyUsed = $this->getMonthlyTokensUsed($user->id);
            if ($monthlyUsed >= $monthlyLimit) {
                return [
                    'allowed' => false,
                    'reason' => 'monthly_token_limit_exceeded',
                    'message' => "Aylık AI token limitinize ({$monthlyLimit}) ulaştınız.",
                    'available_credits' => round($available, 2),
                    'required_credits' => $minCreditsRequired,
                ];
            }
        }

        return [
            'allowed' => true,
            'available_credits' => round($available, 2),
            'required_credits' => $minCreditsRequired,
        ];
    }

    /**
     * Record AI usage and deduct credits from the user's balance.
     */
    public function recordUsageAndCharge(
        User $user,
        ?AiModel $model,
        string $actionType,
        int $inputTokens,
        int $outputTokens,
        int $latencyMs = 0,
        array $metadata = []
    ): array {
        $balance = $this->getBalance($user);

        $rawCostUsd = $model
            ? $model->calculateCost($inputTokens, $outputTokens)
            : round((($inputTokens * 0.15) + ($outputTokens * 0.60)) / 1_000_000, 6);

        $calculatedCredits = $model
            ? $model->calculateChargedCredits($inputTokens, $outputTokens)
            : max(0.25, round($rawCostUsd * 2.0 * 1000, 4));

        // Charge up to available balance so an over-running completion cleanly zeroes the balance
        $creditsToDeduct = min($calculatedCredits, max(0.0, $balance->available_credits));

        $usage = DB::transaction(function () use (
            $user,
            $balance,
            $model,
            $actionType,
            $inputTokens,
            $outputTokens,
            $rawCostUsd,
            $creditsToDeduct,
            $latencyMs,
            $metadata
        ) {
            $usageRecord = AiCreditUsage::create([
                'user_id' => $user->id,
                'ai_model_id' => $model?->id,
                'action_type' => $actionType,
                'provider_slug' => $model?->provider_slug ?? 'nebius',
                'model_id' => $model?->model_id ?? 'meta-llama/Meta-Llama-3.1-70B-Instruct',
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'total_tokens' => $inputTokens + $outputTokens,
                'raw_cost_usd' => $rawCostUsd,
                'credits_charged' => $creditsToDeduct,
                'latency_ms' => $latencyMs,
                'metadata' => $metadata,
            ]);

            if ($creditsToDeduct > 0) {
                $balance->spendCredits(
                    $creditsToDeduct,
                    "AI usage ({$actionType}) via " . ($model?->display_name ?? 'AI Model'),
                    'ai_credit_usage',
                    $usageRecord->id,
                    [
                        'action_type' => $actionType,
                        'model_id' => $model?->model_id,
                        'input_tokens' => $inputTokens,
                        'output_tokens' => $outputTokens,
                        'raw_cost_usd' => $rawCostUsd,
                    ]
                );
            }

            return $usageRecord;
        });

        $freshBalance = $balance->fresh();

        return [
            'usage_id' => $usage->id,
            'credits_charged' => round($creditsToDeduct, 4),
            'raw_cost_usd' => $rawCostUsd,
            'remaining_credits' => round($freshBalance->available_credits, 2),
            'promotional_credits' => round((float) $freshBalance->promotional_credits, 2),
            'purchased_credits' => round((float) $freshBalance->purchased_credits, 2),
        ];
    }

    /**
     * Admin credit adjustment (add or deduct credits).
     */
    public function adminAdjustCredits(
        User $user,
        float $amount,
        string $bucket = 'promotional',
        string $reason = 'Admin credit adjustment',
        ?int $adminId = null
    ): AiCreditTransaction {
        $balance = $this->getBalance($user);

        if ($amount >= 0) {
            if ($bucket === 'purchased') {
                return $balance->addPurchasedCredits(
                    $amount,
                    $reason,
                    'admin_adjust',
                    $adminId,
                    ['admin_id' => $adminId]
                );
            }

            return $balance->addPromotionalCredits(
                $amount,
                'admin_adjust',
                $reason,
                'admin_adjust',
                $adminId,
                ['admin_id' => $adminId]
            );
        }

        $deduct = min(abs($amount), $balance->available_credits);
        $tx = $balance->spendCredits(
            $deduct,
            $reason,
            'admin_adjust',
            $adminId,
            ['admin_id' => $adminId]
        );

        return $tx ?? AiCreditTransaction::create([
            'user_id' => $user->id,
            'type' => 'admin_adjust',
            'bucket' => $bucket,
            'amount' => 0,
            'balance_after' => $balance->available_credits,
            'description' => $reason,
        ]);
    }
}
