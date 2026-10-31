<?php

namespace Database\Seeders;

use App\Models\AiCreditPackage;
use Illuminate\Database\Seeder;

class AiCreditPackageSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            [
                'name' => 'Starter Maker Pack',
                'slug' => 'starter-500',
                'credits' => 500,
                'bonus_credits' => 0,
                'price_usd' => 5.00,
                'whop_product_id' => 'prod_nebius_starter_500',
                'whop_plan_id' => 'plan_nebius_starter_500',
                'whop_checkout_url' => 'https://whop.com/checkout/plan_nebius_starter_500/',
                'is_popular' => false,
                'is_active' => true,
                'sort_order' => 1,
                'description' => 'Starter pack for fast circuit synthesis, pin validation, and embedded C++ firmware generation.',
                'features' => [
                    '500 AI Circuit & Agent Credits',
                    'Flownie Search Mode + CircuitSDK Code Mode',
                    'Tavily Live Datasheet Synthesis',
                    'Never Expires (Purchased Bucket)',
                ],
            ],
            [
                'name' => 'Maker Pro Credit Pack',
                'slug' => 'maker-2000',
                'credits' => 2000,
                'bonus_credits' => 300,
                'price_usd' => 15.00,
                'whop_product_id' => 'prod_nebius_maker_2000',
                'whop_plan_id' => 'plan_nebius_maker_2000',
                'whop_checkout_url' => 'https://whop.com/checkout/plan_nebius_maker_2000/',
                'is_popular' => true,
                'is_active' => true,
                'sort_order' => 2,
                'description' => 'Most popular pack: 2,000 Credits + 300 Bonus Credits for multi-turn robotics and Jetson Nano projects.',
                'features' => [
                    '2,000 Purchased + 300 Bonus AI Credits (2,300 Total)',
                    'Multi-Turn Autonomous Circuit Design Agent',
                    'NVIDIA Nemotron-3 Super 120B & Qwen3 Support',
                    'Priority Electrical Topology Verification',
                ],
            ],
            [
                'name' => 'Robotics Lab Credit Pack',
                'slug' => 'robotics-6000',
                'credits' => 6000,
                'bonus_credits' => 1500,
                'price_usd' => 39.00,
                'whop_product_id' => 'prod_nebius_robotics_6000',
                'whop_plan_id' => 'plan_nebius_robotics_6000',
                'whop_checkout_url' => 'https://whop.com/checkout/plan_nebius_robotics_6000/',
                'is_popular' => false,
                'is_active' => true,
                'sort_order' => 3,
                'description' => 'High-capacity pack for teams, labs, and developers running intensive autonomous hardware simulations.',
                'features' => [
                    '6,000 Purchased + 1,500 Bonus AI Credits (7,500 Total)',
                    'Full Capacity Across All AI Models',
                    'Unlimited Custom 3D Sensor & Datasheet Synthesis',
                    'Full Collaboration in Open Study Groups',
                ],
            ],
        ];

        foreach ($packages as $pkg) {
            AiCreditPackage::firstOrCreate(
                ['slug' => $pkg['slug']],
                $pkg
            );
        }
    }
}
