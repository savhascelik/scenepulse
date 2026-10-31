<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;

class RoleLimit extends Model
{
    use HasFactory;

    public const RESOURCE_SCENARIOS = 'scenarios';
    public const RESOURCE_GROUPS = 'groups';
    public const RESOURCE_AI_LAB = 'ai_lab';
    public const RESOURCE_AI_USAGE = 'ai_usage';
    public const RESOURCE_COMPONENTS = 'components';

    // Legacy resource & key aliases for GroupController / ScenarioController / CustomComponentController
    public const RESOURCE_STUDY_GROUP = 'study_group';
    public const RESOURCE_CIRCUIT_SCENARIO = 'circuit_scenario';
    public const RESOURCE_CUSTOM_COMPONENT = 'custom_component';
    public const RESOURCE_AI_DAILY = 'ai_daily';
    public const LIMIT_MAX_DAILY = 'max_daily';
    public const LIMIT_MAX_TOTAL = 'max_saved_scenarios';
    public const LIMIT_MAX_NODES = 'max_components_per_scenario';
    public const LIMIT_INITIAL_CREDIT_BALANCE = 'initial_credit_balance';

    public static function setLimit(int $roleId, string $resourceType, string $limitKey, int $limitValue, ?string $description = null): self
    {
        return self::updateOrCreate(
            [
                'role_id' => $roleId,
                'resource_type' => $resourceType,
                'limit_key' => $limitKey,
            ],
            [
                'limit_value' => $limitValue,
                'description' => $description,
            ]
        );
    }

    protected $fillable = [
        'role_id',
        'resource_type',
        'limit_key',
        'limit_value',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'role_id' => 'integer',
            'limit_value' => 'integer',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public static function getAvailableResources(): array
    {
        return [
            self::RESOURCE_AI_USAGE => 'AI Credit & Token Usage',
            self::RESOURCE_AI_LAB => 'AI Studio & Agent Loop',
            self::RESOURCE_SCENARIOS => '3D Circuit Scenarios / Workspaces',
            self::RESOURCE_GROUPS => 'Open Study Groups',
            self::RESOURCE_COMPONENTS => 'Custom 3D Components & Datasheets',
        ];
    }

    public static function getAvailableKeys(?string $resourceType = null): array
    {
        $keys = [
            self::RESOURCE_AI_USAGE => [
                'initial_credit_balance' => 'Initial Signup AI Credit Grant',
                'daily_token_limit' => 'Daily Token Limit (-1 = Unlimited)',
                'monthly_token_limit' => 'Monthly Token Limit (-1 = Unlimited)',
                'max_tokens_per_request' => 'Max Tokens Per Single AI Request',
            ],
            self::RESOURCE_AI_LAB => [
                'daily_ai_requests' => 'Daily AI Studio Calls (-1 = Unlimited)',
                'max_agent_rounds' => 'Max Multi-Round Agent Loop Turns',
                'allow_code_mode' => 'Allow CircuitSDK Code Mode (1 = Yes, 0 = No)',
            ],
            self::RESOURCE_SCENARIOS => [
                'max_saved_scenarios' => 'Max Saved 3D Workspaces (-1 = Unlimited)',
                'max_components_per_scenario' => 'Max 3D Components Per Workspace',
            ],
            self::RESOURCE_GROUPS => [
                'max_owned_groups' => 'Max Owned Study Groups',
                'max_joined_groups' => 'Max Joined Study Groups',
            ],
            self::RESOURCE_COMPONENTS => [
                'daily_datasheet_syntheses' => 'Daily Tavily Datasheet Syntheses',
            ],
        ];

        if ($resourceType !== null) {
            return $keys[$resourceType] ?? [];
        }

        return $keys;
    }

    public static function getResourceTypes(): array
    {
        return self::getAvailableResources();
    }

    public static function getLimitKeys(?string $resourceType = null): array
    {
        if ($resourceType !== null) {
            return self::getAvailableKeys($resourceType);
        }

        $flat = [];
        foreach (self::getAvailableKeys() as $groupKeys) {
            foreach ($groupKeys as $k => $label) {
                $flat[$k] = $label;
            }
        }

        return $flat;
    }

    protected static function booted(): void
    {
        static::saved(function (self $limit) {
            Cache::forget("role_limit:{$limit->role_id}:{$limit->resource_type}:{$limit->limit_key}");
        });

        static::deleted(function (self $limit) {
            Cache::forget("role_limit:{$limit->role_id}:{$limit->resource_type}:{$limit->limit_key}");
        });
    }
}
