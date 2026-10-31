<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\RoleLimit;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\PermissionRegistrar;

class UserAccessService
{
    public function __construct(
        private readonly LimitService $limitService,
        private readonly AiCreditService $creditService
    ) {}

    public function isAdmin(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function isPro(User $user): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }
        if ($user->hasRole('pro') || $user->hasRole('judge')) {
            return true;
        }
        $planSlug = $this->getNormalizedPlanSlug($user);
        return in_array($planSlug, ['pro', 'pro-robotics', 'admin'], true);
    }

    public function getNormalizedPlanSlug(User $user, ?Subscription $activeSub = null): string
    {
        if ($this->isAdmin($user)) {
            return 'admin';
        }

        $sub = $activeSub ?? $user->activeSubscription()->with('plan')->first();
        if ($sub && $sub->plan) {
            return $sub->plan->slug;
        }

        if ($user->hasRole('pro')) {
            return 'pro-robotics';
        }

        return 'free-maker';
    }

    public function syncUserSubscriptionRole(User $user): void
    {
        $isAdmin = $this->isAdmin($user);
        $activeSubscription = $user->activeSubscription()->with('plan.role')->first();

        if ($activeSubscription && $activeSubscription->plan?->role) {
            $targetRole = $activeSubscription->plan->role->name;
        } else {
            $targetRole = 'free';
        }

        $roles = $isAdmin ? ['admin', $targetRole] : [$targetRole];
        $user->syncRoles($roles);
        $this->clearUserAccessCache($user);
    }

    public function clearUserAccessCache(User $user): void
    {
        Cache::forget("user.{$user->id}.limits");
        Cache::forget("user_access_profile_{$user->id}");
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user->unsetRelation('roles');
        $user->unsetRelation('permissions');
        $user->unsetRelation('activeSubscription');
    }

    public function getAccessProfile(User $user): array
    {
        $isAdmin = $this->isAdmin($user);
        $roles = $user->getRoleNames()->values()->all();
        $primaryRole = $isAdmin ? 'admin' : ($roles[0] ?? 'free');

        $activeSub = $user->relationLoaded('activeSubscription')
            ? $user->activeSubscription
            : $user->activeSubscription()->with('plan')->first();

        $planModel = $activeSub?->plan
            ?? Plan::query()->where('slug', $this->isPro($user) ? 'pro-robotics' : 'free-maker')->first();

        $scenariosQuota = LimitService::canCreate(RoleLimit::RESOURCE_SCENARIOS, $user);
        $groupsQuota = LimitService::canCreate(RoleLimit::RESOURCE_GROUPS, $user);
        $componentsQuota = LimitService::canCreate(RoleLimit::RESOURCE_COMPONENTS, $user);
        $credits = $this->creditService->getBalanceSummary($user);

        return [
            'role' => $primaryRole,
            'roles' => $roles,
            'is_admin' => $isAdmin,
            'is_pro' => $this->isPro($user),
            'plan' => $planModel ? [
                'id' => $planModel->id,
                'name' => $planModel->name,
                'slug' => $planModel->slug,
                'monthly_credits' => (int) $planModel->monthly_credits,
                'initial_credits' => (int) $planModel->initial_credits,
                'price_usd' => (float) $planModel->price_usd,
            ] : [
                'id' => null,
                'name' => $this->isPro($user) ? 'Pro Robotics & AI Architect' : 'Free Maker',
                'slug' => $this->getNormalizedPlanSlug($user, $activeSub),
                'monthly_credits' => $this->isPro($user) ? 2500 : 250,
                'initial_credits' => $this->isPro($user) ? 2500 : 250,
                'price_usd' => $this->isPro($user) ? 19.0 : 0.0,
            ],
            'permissions' => $user->getAllPermissions()->pluck('name')->values()->all(),
            'quotas' => [
                'scenarios' => [
                    'used' => $scenariosQuota['used'],
                    'limit' => $scenariosQuota['limit'],
                    'remaining' => $scenariosQuota['remaining'],
                    'unlimited' => $scenariosQuota['unlimited'],
                    'can_create' => $scenariosQuota['allowed'],
                ],
                'groups' => [
                    'used' => $groupsQuota['used'],
                    'limit' => $groupsQuota['limit'],
                    'remaining' => $groupsQuota['remaining'],
                    'unlimited' => $groupsQuota['unlimited'],
                    'can_create' => $groupsQuota['allowed'],
                ],
                'custom_components' => [
                    'used' => $componentsQuota['used'],
                    'limit' => $componentsQuota['limit'],
                    'remaining' => $componentsQuota['remaining'],
                    'unlimited' => $componentsQuota['unlimited'],
                    'can_create' => $componentsQuota['allowed'],
                ],
                'max_nodes_per_scenario' => $this->limitService->getUserLimit(
                    $user,
                    RoleLimit::RESOURCE_SCENARIOS,
                    'max_components_per_scenario'
                ),
            ],
            'credits' => [
                'available' => $credits['available_credits'],
                'promotional' => $credits['promotional_credits'],
                'purchased' => $credits['purchased_credits'],
                'spent' => $credits['total_spent'],
            ],
        ];
    }
}
