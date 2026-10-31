<?php

namespace App\Models;

use App\Services\LimitService;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected string $guard_name = 'web';

    protected static function booted(): void
    {
        static::created(function (self $user) {
            try {
                if ($user->roles()->count() === 0 && Role::where('name', 'free')->where('guard_name', 'web')->exists()) {
                    $user->assignRole('free');
                }
            } catch (\Throwable) {
                // Ignore if roles table is not migrated yet
            }
        });
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasRole('admin');
    }

    protected $fillable = [
        'name',
        'email',
        'password',
        'settings',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'settings' => 'array',
        ];
    }

    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    public function aiUsageLogs(): HasMany
    {
        return $this->hasMany(AiUsageLog::class);
    }

    public function creditBalance(): HasOne
    {
        return $this->hasOne(AiCreditBalance::class);
    }

    public function creditTransactions(): HasMany
    {
        return $this->hasMany(AiCreditTransaction::class);
    }

    public function creditUsages(): HasMany
    {
        return $this->hasMany(AiCreditUsage::class);
    }

    public function creditOrders(): HasMany
    {
        return $this->hasMany(AiCreditOrder::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)
            ->where('status', 'active')
            ->latestOfMany();
    }

    public function ensureInitialCreditGrant(): AiCreditBalance
    {
        $balance = AiCreditBalance::getOrCreateForUser($this->id);
        $roleName = $this->getRoleNames()->first() ?? 'free';

        $defaultRoleGrant = match ($roleName) {
            'judge' => 400.0,
            'admin' => 500.0,
            'pro' => 2500.0,
            default => 100.0,
        };

        if ((float) $balance->total_earned_promotional <= 0) {
            /** @var LimitService $limitService */
            $limitService = app(LimitService::class);
            $initialGrant = (float) $limitService->getUserLimit(
                $this,
                RoleLimit::RESOURCE_AI_USAGE,
                'initial_credit_balance'
            );

            if ($initialGrant <= 0 || ($roleName === 'judge' && $initialGrant == 5000.0) || ($roleName === 'free' && $initialGrant == 250.0)) {
                $initialGrant = $defaultRoleGrant;
            }

            $balance->addPromotionalCredits(
                (float) $initialGrant,
                'signup_grant',
                "Initial AI Credit grant for role '{$roleName}' ({$initialGrant} credits)",
                'role_limit',
                null,
                ['role' => $roleName, 'initial_credit_balance' => $initialGrant]
            );
        }

        return $balance->fresh();
    }
}
