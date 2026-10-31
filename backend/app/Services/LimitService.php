<?php

namespace App\Services;

use App\Models\RoleLimit;
use App\Models\ScenePulseImpulse;
use App\Models\User;
use App\Models\VideoItem;
use Illuminate\Support\Facades\Cache;

class LimitService
{
    private const CACHE_TTL = 1800;

    public const DEFAULT_MAX_AGENT_ROUNDS = 10;

    public const HARD_MAX_AGENT_ROUNDS = 30;

    private const DEFAULT_LIMITS = [
        RoleLimit::RESOURCE_AI_USAGE => [
            'initial_credit_balance' => 250,
            'daily_token_limit' => 250000,
            'monthly_token_limit' => 2500000,
            'max_tokens_per_request' => 16384,
        ],
        RoleLimit::RESOURCE_AI_LAB => [
            'daily_ai_requests' => 50,
            'max_agent_rounds' => 10,
            'allow_code_mode' => 1,
        ],
    ];

    public function getUserLimit(User $user, string $resourceType, string $limitKey): int
    {
        if ($user->hasRole('admin')) {
            if ($limitKey === 'initial_credit_balance') {
                return 10000;
            }
            if ($limitKey === 'max_agent_rounds') {
                return 20;
            }
            if ($limitKey === 'allow_code_mode') {
                return 1;
            }
            return -1;
        }

        $roles = $user->roles;
        if ($roles->isEmpty()) {
            return self::DEFAULT_LIMITS[$resourceType][$limitKey] ?? 0;
        }

        $maxLimit = null;

        foreach ($roles as $role) {
            $value = $this->getRoleLimit($role->id, $resourceType, $limitKey);

            if ($value === -1) {
                return -1;
            }

            if ($maxLimit === null || $value > $maxLimit) {
                $maxLimit = $value;
            }
        }

        return $maxLimit ?? (self::DEFAULT_LIMITS[$resourceType][$limitKey] ?? 0);
    }

    public function getRoleLimit(int $roleId, string $resourceType, string $limitKey): int
    {
        $cacheKey = "role_limit:{$roleId}:{$resourceType}:{$limitKey}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($roleId, $resourceType, $limitKey) {
            $limit = RoleLimit::query()
                ->where('role_id', $roleId)
                ->where('resource_type', $resourceType)
                ->where('limit_key', $limitKey)
                ->first();

            return $limit ? $limit->limit_value : (self::DEFAULT_LIMITS[$resourceType][$limitKey] ?? 0);
        });
    }

    public function canPerformAction(User $user, string $resourceType, string $limitKey, int $currentUsage): bool
    {
        $limit = $this->getUserLimit($user, $resourceType, $limitKey);

        if ($limit === -1) {
            return true;
        }

        return $currentUsage < $limit;
    }

    public function getUserAllLimits(User $user): array
    {
        $summary = [];
        foreach (self::DEFAULT_LIMITS as $resourceType => $keys) {
            $summary[$resourceType] = [];
            foreach (array_keys($keys) as $limitKey) {
                $summary[$resourceType][$limitKey] = $this->getUserLimit($user, $resourceType, $limitKey);
            }
        }

        return $summary;
    }

    public static function canCreate(string $resourceType, User $user): array
    {
        return [
            'allowed' => true,
            'limit' => -1,
            'used' => ScenePulseImpulse::count(),
            'current' => ScenePulseImpulse::count(),
            'remaining' => -1,
            'unlimited' => true,
            'reason' => null,
            'message' => null,
        ];
    }

    public static function getLimitInfo(string $resourceType, User $user): array
    {
        $quota = self::canCreate($resourceType, $user);

        return [
            'resource_type' => $resourceType,
            'limits' => [
                'max_total' => $quota['limit'],
                'max_total_unlimited' => $quota['unlimited'],
            ],
            'usage' => [
                'total' => $quota['used'],
            ],
            'used' => $quota['used'],
            'limit' => $quota['limit'],
            'remaining' => $quota['remaining'],
            'unlimited' => $quota['unlimited'],
            'can_create' => $quota['allowed'],
            'reason' => $quota['reason'],
            'message' => $quota['message'],
        ];
    }

    public static function getUsageOverview(User $user): array
    {
        /** @var AiCreditService $creditService */
        $creditService = app(AiCreditService::class);
        $roleName = $user->getRoleNames()->first() ?? 'free';

        return [
            'role' => $roleName,
            'resources' => [
                'videos' => [
                    'label' => 'Active Video Streams',
                    'icon' => 'video',
                    'used' => VideoItem::count(),
                    'limit' => -1,
                    'remaining' => -1,
                    'unlimited' => true,
                    'can_create' => true,
                    'percentage' => 0,
                ],
                'impulses' => [
                    'label' => 'Cached Qloo Scene Impulses',
                    'icon' => 'sparkles',
                    'used' => ScenePulseImpulse::count(),
                    'limit' => -1,
                    'remaining' => -1,
                    'unlimited' => true,
                    'can_create' => true,
                    'percentage' => 0,
                ],
            ],
            'credits' => $creditService->getBalanceSummary($user),
        ];
    }
}
