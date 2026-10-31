<?php

namespace Database\Seeders;

use App\Models\RoleLimit;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleLimitSeeder extends Seeder
{
    public function run(): void
    {
        $matrix = [
            'unverified' => [
                [RoleLimit::RESOURCE_AI_USAGE, 'initial_credit_balance', 50, 'Unverified initial AI credits'],
                [RoleLimit::RESOURCE_AI_USAGE, 'daily_token_limit', 25000, 'Unverified daily token cap'],
                [RoleLimit::RESOURCE_AI_USAGE, 'monthly_token_limit', 100000, 'Unverified monthly token cap'],
                [RoleLimit::RESOURCE_AI_USAGE, 'max_tokens_per_request', 4096, 'Max tokens per AI request'],
                [RoleLimit::RESOURCE_SCENARIOS, 'max_saved_scenarios', 2, 'Unverified users can save up to 2 scenarios'],
                [RoleLimit::RESOURCE_SCENARIOS, 'max_components_per_scenario', 15, 'Max 15 components per scenario'],
                [RoleLimit::RESOURCE_GROUPS, 'max_owned_groups', 0, 'Cannot create groups until verified'],
                [RoleLimit::RESOURCE_GROUPS, 'max_joined_groups', 2, 'Can join up to 2 public groups'],
                [RoleLimit::RESOURCE_AI_LAB, 'daily_ai_requests', 5, '5 AI calls per day'],
                [RoleLimit::RESOURCE_AI_LAB, 'max_agent_rounds', 3, 'Max 3 rounds per agent loop'],
                [RoleLimit::RESOURCE_AI_LAB, 'allow_code_mode', 1, 'Allow CircuitSDK Code Mode'],
                [RoleLimit::RESOURCE_COMPONENTS, 'daily_datasheet_syntheses', 2, '2 datasheet lookups per day'],
            ],
            'free' => [
                [RoleLimit::RESOURCE_AI_USAGE, 'initial_credit_balance', 100, 'Initial signup AI credit grant for Free Maker tier (100 credits)'],
                [RoleLimit::RESOURCE_AI_USAGE, 'daily_token_limit', 250000, 'Daily token limit for Free tier'],
                [RoleLimit::RESOURCE_AI_USAGE, 'monthly_token_limit', 2500000, 'Monthly token limit for Free tier'],
                [RoleLimit::RESOURCE_AI_USAGE, 'max_tokens_per_request', 16384, 'Max tokens per AI request'],
                [RoleLimit::RESOURCE_SCENARIOS, 'max_saved_scenarios', 25, 'Free makers can save up to 25 3D circuits'],
                [RoleLimit::RESOURCE_SCENARIOS, 'max_components_per_scenario', 60, 'Max 60 components per circuit'],
                [RoleLimit::RESOURCE_GROUPS, 'max_owned_groups', 5, 'Every user can create up to 5 open study groups'],
                [RoleLimit::RESOURCE_GROUPS, 'max_joined_groups', 25, 'Can join up to 25 study groups'],
                [RoleLimit::RESOURCE_AI_LAB, 'daily_ai_requests', 50, '50 AI Circuit/Diagnose calls per day'],
                [RoleLimit::RESOURCE_AI_LAB, 'max_agent_rounds', 10, 'Max 10 rounds per Flownie AI agent loop (extendable via askUser)'],
                [RoleLimit::RESOURCE_AI_LAB, 'allow_code_mode', 1, 'Allow CircuitSDK Code Mode execution'],
                [RoleLimit::RESOURCE_COMPONENTS, 'daily_datasheet_syntheses', 25, '25 Tavily datasheet syntheses per day'],
            ],
            'pro' => [
                [RoleLimit::RESOURCE_AI_USAGE, 'initial_credit_balance', 2500, 'Initial AI credit grant for Pro tier'],
                [RoleLimit::RESOURCE_AI_USAGE, 'daily_token_limit', -1, 'Unlimited daily tokens (governed by AI credit balance)'],
                [RoleLimit::RESOURCE_AI_USAGE, 'monthly_token_limit', -1, 'Unlimited monthly tokens'],
                [RoleLimit::RESOURCE_AI_USAGE, 'max_tokens_per_request', 65536, 'Max tokens per AI request'],
                [RoleLimit::RESOURCE_SCENARIOS, 'max_saved_scenarios', -1, 'Unlimited saved circuits'],
                [RoleLimit::RESOURCE_SCENARIOS, 'max_components_per_scenario', 250, 'Up to 250 components per circuit'],
                [RoleLimit::RESOURCE_GROUPS, 'max_owned_groups', -1, 'Unlimited study groups'],
                [RoleLimit::RESOURCE_GROUPS, 'max_joined_groups', -1, 'Unlimited group memberships'],
                [RoleLimit::RESOURCE_AI_LAB, 'daily_ai_requests', 500, '500 AI calls per day'],
                [RoleLimit::RESOURCE_AI_LAB, 'max_agent_rounds', 15, 'Up to 15 rounds per Flownie AI agent loop'],
                [RoleLimit::RESOURCE_AI_LAB, 'allow_code_mode', 1, 'Allow CircuitSDK Code Mode execution'],
                [RoleLimit::RESOURCE_COMPONENTS, 'daily_datasheet_syntheses', 200, '200 datasheet syntheses per day'],
            ],
            'judge' => [
                [RoleLimit::RESOURCE_AI_USAGE, 'initial_credit_balance', 400, 'Hackathon Judge AI credit grant (400 credits)'],
                [RoleLimit::RESOURCE_AI_USAGE, 'daily_token_limit', -1, 'Unlimited daily tokens for Hackathon Judges'],
                [RoleLimit::RESOURCE_AI_USAGE, 'monthly_token_limit', -1, 'Unlimited monthly tokens'],
                [RoleLimit::RESOURCE_AI_USAGE, 'max_tokens_per_request', 65536, 'Max tokens per AI request'],
                [RoleLimit::RESOURCE_SCENARIOS, 'max_saved_scenarios', -1, 'Unlimited saved circuits for Hackathon Judges'],
                [RoleLimit::RESOURCE_SCENARIOS, 'max_components_per_scenario', -1, 'Unlimited components per circuit'],
                [RoleLimit::RESOURCE_GROUPS, 'max_owned_groups', -1, 'Unlimited study groups'],
                [RoleLimit::RESOURCE_GROUPS, 'max_joined_groups', -1, 'Unlimited group memberships'],
                [RoleLimit::RESOURCE_AI_LAB, 'daily_ai_requests', -1, 'Unlimited AI Lab calls for Judges'],
                [RoleLimit::RESOURCE_AI_LAB, 'max_agent_rounds', 20, 'Max 20 rounds per Flownie AI agent loop'],
                [RoleLimit::RESOURCE_AI_LAB, 'allow_code_mode', 1, 'Allow CircuitSDK Code Mode execution'],
                [RoleLimit::RESOURCE_COMPONENTS, 'daily_datasheet_syntheses', -1, 'Unlimited Tavily datasheet syntheses'],
            ],
            'admin' => [
                [RoleLimit::RESOURCE_AI_USAGE, 'initial_credit_balance', 500, 'Studio Admin AI credit grant'],
                [RoleLimit::RESOURCE_AI_USAGE, 'daily_token_limit', -1, 'Unlimited'],
                [RoleLimit::RESOURCE_AI_USAGE, 'monthly_token_limit', -1, 'Unlimited'],
                [RoleLimit::RESOURCE_AI_USAGE, 'max_tokens_per_request', 128000, 'Unlimited'],
                [RoleLimit::RESOURCE_SCENARIOS, 'max_saved_scenarios', -1, 'Unlimited'],
                [RoleLimit::RESOURCE_SCENARIOS, 'max_components_per_scenario', -1, 'Unlimited'],
                [RoleLimit::RESOURCE_GROUPS, 'max_owned_groups', -1, 'Unlimited'],
                [RoleLimit::RESOURCE_GROUPS, 'max_joined_groups', -1, 'Unlimited'],
                [RoleLimit::RESOURCE_AI_LAB, 'daily_ai_requests', -1, 'Unlimited'],
                [RoleLimit::RESOURCE_AI_LAB, 'max_agent_rounds', 20, 'Unlimited'],
                [RoleLimit::RESOURCE_AI_LAB, 'allow_code_mode', 1, 'Allow CircuitSDK Code Mode execution'],
                [RoleLimit::RESOURCE_COMPONENTS, 'daily_datasheet_syntheses', -1, 'Unlimited'],
            ],
        ];

        foreach ($matrix as $roleName => $limits) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();
            if (! $role) {
                continue;
            }

            foreach ($limits as [$resourceType, $limitKey, $limitValue, $description]) {
                RoleLimit::updateOrCreate(
                    [
                        'role_id' => $role->id,
                        'resource_type' => $resourceType,
                        'limit_key' => $limitKey,
                    ],
                    [
                        'limit_value' => $limitValue,
                        'description' => $description,
                    ]
                );
            }
        }
    }
}
