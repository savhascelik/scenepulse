<?php

namespace Tests\Unit;

use App\Models\AiModel;
use App\Models\DatasheetCache;
use App\Services\FunctionFilter;
use PHPUnit\Framework\TestCase;

class AiModelPricingAndFilterUnitTest extends TestCase
{
    public function test_ai_model_calculates_usd_cost_and_minimum_floor_credits_deterministically(): void
    {
        $model = new AiModel([
            'provider_slug' => 'nebius',
            'model_id' => 'nvidia/nemotron-3-super-120b-a12b',
            'input_cost_per_1m' => 0.300000,
            'output_cost_per_1m' => 0.900000,
            'token_multiplier' => 1.80,
            'input_multiplier' => 1.80,
            'output_multiplier' => 2.00,
        ]);

        // Small call (50 input, 20 output) enforces the 0.2500 minimum floor credit charge
        $this->assertSame(0.2500, $model->calculateChargedCredits(50, 20));

        // Larger call (100k input, 50k output) computes exact weighted credit cost
        $usdCost = $model->calculateCost(100_000, 50_000);
        $this->assertSame(0.075, $usdCost);

        $chargedCredits = $model->calculateChargedCredits(100_000, 50_000);
        // (0.1 * 0.30 * 1.80 + 0.05 * 0.90 * 2.00) * 1000 = (0.054 + 0.090) * 1000 = 144.0
        $this->assertSame(144.0, $chargedCredits);
    }

    public function test_datasheet_cache_normalizes_part_keys_safely(): void
    {
        $this->assertSame('BMP280', DatasheetCache::normalizePartKey(' bmp280 '));
        $this->assertSame('HC-SR04_V2', DatasheetCache::normalizePartKey('hc-sr04_v2!@#'));
        $this->assertSame('UNKNOWN', DatasheetCache::normalizePartKey('   $$$   '));
    }

    public function test_function_filter_resolves_nested_capability_targets(): void
    {
        $filter = new FunctionFilter();

        $direct = $filter->resolveCallTarget([
            'name' => 'searchCapabilities',
            'arguments' => ['query' => 'ultrasonic'],
        ]);
        $this->assertSame('searchCapabilities', $direct['outer_name']);
        $this->assertSame('searchCapabilities', $direct['target_name']);
        $this->assertFalse($direct['is_wrapped']);

        $wrapped = $filter->resolveCallTarget([
            'name' => 'callCapability',
            'arguments' => [
                'name' => 'sdk.connectPins',
                'args' => ['fromPinId' => 'uno:D9', 'toPinId' => 'servo_1:SIG'],
            ],
        ]);
        $this->assertSame('callCapability', $wrapped['outer_name']);
        $this->assertSame('connectPins', $wrapped['target_name']);
        $this->assertTrue($wrapped['is_wrapped']);
    }
}
