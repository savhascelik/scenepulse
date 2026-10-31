<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $freeRole = Role::where('name', 'free')->where('guard_name', 'web')->first();
        $proRole = Role::where('name', 'pro')->where('guard_name', 'web')->first();

        $plans = [
            [
                'slug' => 'free-maker',
                'name' => 'Free Maker Plan',
                'description' => '3D circuit assembly, closed-loop simulation, and 100 initial AI credits for makers.',
                'role_id' => $freeRole?->id,
                'whop_plan_id' => null,
                'price' => 0.00,
                'price_suffix' => '/month',
                'currency' => 'USD',
                'monthly_points' => 100.0000,
                'bonus_points' => 0.0000,
                'features' => [
                    '100 Initial AI Credits',
                    '25 Saved 3D Workspaces',
                    '5 Open Study Groups',
                    'Arduino Uno, ESP32 & Jetson Nano Simulation',
                ],
                'cta_text' => 'Start Free',
                'is_highlighted' => false,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'slug' => 'pro-robotics',
                'name' => 'Pro Robotics & AI Architect',
                'description' => 'Unlimited 3D workspaces, high AI agent round limits, and 2,500 monthly AI credits.',
                'role_id' => $proRole?->id,
                'whop_plan_id' => 'plan_nebius_pro_monthly',
                'price' => 19.00,
                'price_suffix' => '/month',
                'currency' => 'USD',
                'monthly_points' => 2500.0000,
                'bonus_points' => 500.0000,
                'features' => [
                    '2,500 Monthly + 500 Welcome AI Credits',
                    'Unlimited 3D Workspaces & Study Groups',
                    '15-Round Flownie Code Mode & Search Mode Agent',
                    '200 Daily Tavily Live Datasheet Syntheses',
                ],
                'cta_text' => 'Upgrade to Pro',
                'is_highlighted' => true,
                'is_active' => true,
                'sort_order' => 2,
            ],
        ];

        foreach ($plans as $planData) {
            Plan::firstOrCreate(
                ['slug' => $planData['slug']],
                $planData
            );
        }
    }
}
