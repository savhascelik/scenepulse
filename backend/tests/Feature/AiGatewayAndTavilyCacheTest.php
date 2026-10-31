<?php

namespace Tests\Feature;

use App\Models\AiCreditBalance;
use App\Models\AiModel;
use App\Models\User;
use Database\Seeders\AiCreditPackageSeeder;
use Database\Seeders\AiModelSeeder;
use Database\Seeders\RoleLimitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiGatewayAndTavilyCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLimitSeeder::class);
        $this->seed(AiModelSeeder::class);
        $this->seed(AiCreditPackageSeeder::class);
    }

    public function test_diagnose_circuit_deducts_ai_credits_and_returns_structured_diagnosis(): void
    {
        $user = User::create([
            'name' => 'Circuit Debugger',
            'email' => 'debugger@example.com',
            'password' => 'password123',
        ]);
        $user->ensureInitialCreditGrant();
        $token = $user->createToken('debug')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/ai/diagnose-circuit', [
                'scenario_data' => [
                    'components' => [['id' => 'led-1', 'type' => 'LED_RED']],
                    'connections' => [['from' => 'uno:5V', 'to' => 'led-1:A']],
                ],
                'solver_telemetry' => ['short_circuit' => false],
                'question' => 'Check LED resistor protection',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('diagnosis.status', 'warning')
            ->assertJsonPath('meta.used_byok', false);

        $this->assertGreaterThan(0, (float) $response->json('meta.credits_charged'));
        $this->assertLessThan(250.0, (float) $response->json('meta.remaining_credits'));
    }

    public function test_flownie_multi_round_agent_turn_executes_search_mode_and_code_mode_and_charges_credits(): void
    {
        $user = User::create([
            'name' => 'Flownie Architect',
            'email' => 'architect@example.com',
            'password' => 'password123',
        ]);
        $user->ensureInitialCreditGrant();
        $token = $user->createToken('agent')->plainTextToken;

        // Round 1: Search Mode & Schema Discovery
        $round1 = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/ai/agent-turn', [
                'prompt' => 'Arduino Uno üzerinde ultrasonik mesafe sensörü ve servo motor ile engel kapısı kur ve kodunu yaz',
                'round' => 1,
                'canvas_summary' => ['boardId' => 'board.arduino_uno'],
                'tool_results' => [],
            ]);

        $round1->assertStatus(200)
            ->assertJsonPath('turn.tool_calls.0.name', 'searchCapabilities')
            ->assertJsonPath('round_budget.round', 1)
            ->assertJsonPath('round_budget.max_rounds', 10)
            ->assertJsonPath('round_budget.remaining_rounds', 9);

        // Round 2: Code Mode (`executeCode` with CircuitSDK)
        $round2 = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/ai/agent-turn', [
                'prompt' => 'Arduino Uno üzerinde ultrasonik mesafe sensörü ve servo motor ile engel kapısı kur ve kodunu yaz',
                'round' => 2,
                'max_rounds' => 10,
                'remaining_rounds' => 8,
                'canvas_summary' => ['boardId' => 'board.arduino_uno'],
                'tool_results' => [
                    ['name' => 'searchCapabilities', 'result' => ['totalMatches' => 3]],
                ],
            ]);

        $round2->assertStatus(200)
            ->assertJsonPath('turn.tool_calls.0.name', 'executeCode')
            ->assertJsonPath('round_budget.round', 2)
            ->assertJsonPath('round_budget.remaining_rounds', 8);

        $code = (string) $round2->json('turn.tool_calls.0.args.code');
        $this->assertStringContainsString("circuit.addComponent('sensor.ultrasonic'", $code);
        $this->assertStringContainsString("circuit.addComponent('motor.servo'", $code);
        $this->assertStringContainsString('circuit.connectWire(', $code);
        $this->assertStringContainsString('circuit.setCode(', $code);
    }

    public function test_agent_turn_requests_extra_rounds_via_ask_user_when_budget_is_low_and_allows_user_approved_extension_beyond_10_rounds(): void
    {
        $user = User::create([
            'name' => 'Round Budget Engineer',
            'email' => 'rounds@example.com',
            'password' => 'password123',
        ]);
        $user->ensureInitialCreditGrant();
        $token = $user->createToken('round-budget')->plainTextToken;

        // 1. At round 9 of 10 (remaining_rounds = 1) before executeCode has run, agent asks user for +5 extra rounds
        $lowBudgetTurn = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/ai/agent-turn', [
                'prompt' => 'Arduino Uno üzerinde ultrasonik sensör, servo motor ve OLED ekranlı gelişmiş devre kur',
                'round' => 9,
                'max_rounds' => 10,
                'remaining_rounds' => 1,
                'canvas_summary' => ['boardId' => 'board.arduino_uno'],
                'tool_results' => [
                    ['name' => 'searchCapabilities', 'result' => ['totalMatches' => 4]],
                ],
            ]);

        $lowBudgetTurn->assertStatus(200)
            ->assertJsonPath('tool_calls.0.name', 'askUser')
            ->assertJsonPath('tool_calls.0.args.reason', 'extend_rounds')
            ->assertJsonPath('tool_calls.0.args.requested_extra_rounds', 5);

        // 2. Without user approval, round 11 is blocked by default 10-round limit (HTTP 403)
        $blockedRound11 = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/ai/agent-turn', [
                'prompt' => 'Devam et',
                'round' => 11,
                'max_rounds' => 10,
            ]);

        $blockedRound11->assertStatus(403)
            ->assertJsonPath('error', 'max_agent_rounds_exceeded')
            ->assertJsonPath('limit', 10);

        // 3. With user approval (user_approved_max_rounds = 15, round_extension_granted = true), round 11 succeeds and executes Code Mode!
        $approvedRound11 = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/ai/agent-turn', [
                'prompt' => 'Arduino Uno üzerinde ultrasonik sensör, servo motor ve OLED ekranlı gelişmiş devre kur',
                'round' => 11,
                'max_rounds' => 15,
                'remaining_rounds' => 4,
                'user_approved_max_rounds' => 15,
                'round_extension_granted' => true,
                'user_answer' => '+5 Tur İzni Ver ve Devam Et',
                'canvas_summary' => ['boardId' => 'board.arduino_uno'],
                'tool_results' => [
                    ['name' => 'searchCapabilities', 'result' => ['totalMatches' => 4]],
                    ['name' => 'askUser', 'result' => ['answer' => '+5 Tur İzni Ver ve Devam Et', 'extended_rounds' => 5]],
                ],
            ]);

        $approvedRound11->assertStatus(200)
            ->assertJsonPath('round_budget.round', 11)
            ->assertJsonPath('round_budget.max_rounds', 15)
            ->assertJsonPath('round_budget.remaining_rounds', 4)
            ->assertJsonPath('round_budget.extension_granted', true)
            ->assertJsonPath('tool_calls.0.name', 'executeCode');

        // 3b. Verify session_knowledge accepts both string (legacy/markdown) and array payloads without 422
        $stringKnowledgeTurn = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/ai/agent-turn', [
                'prompt' => 'Arduino Uno üzerinde LED blink devresi kur',
                'round' => 1,
                'max_rounds' => 10,
                'session_knowledge' => '',
            ]);
        $stringKnowledgeTurn->assertStatus(200);

        $arrayKnowledgeTurn = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/ai/agent-turn', [
                'prompt' => 'Arduino Uno üzerinde LED blink devresi kur',
                'round' => 2,
                'max_rounds' => 10,
                'session_knowledge' => ['@component:board.arduino_uno -> name="Arduino Uno R3"'],
            ]);
        $arrayKnowledgeTurn->assertStatus(200);

        // 4. Verify FunctionFilter callCapability target resolution & inspect-mode rejection reporting
        $filter = app(\App\Services\FunctionFilter::class);
        $partitioned = $filter->partitionToolCalls($user, [
            ['name' => 'callCapability', 'args' => ['name' => 'getComponentSchema', 'args' => ['componentId' => 'board.arduino_uno']]],
            ['name' => 'callCapability', 'args' => ['name' => 'addComponent', 'args' => ['typeId' => 'led.single']]],
            ['name' => 'callCapability', 'args' => ['name' => 'nonExistentHackTool', 'args' => []]],
            ['name' => 'executeCode', 'args' => ['code' => 'circuit.clearAddedComponents()']],
        ], 'inspect');

        $this->assertCount(1, $partitioned['allowed']);
        $this->assertSame('callCapability', $partitioned['allowed'][0]['name']);
        $this->assertCount(3, $partitioned['rejected']);
    }

    public function test_zero_credit_balance_returns_402_insufficient_credits_until_whop_topup_completes(): void
    {
        $user = User::create([
            'name' => 'Zero Credit User',
            'email' => 'zero@example.com',
            'password' => 'password123',
        ]);
        $balance = $user->ensureInitialCreditGrant();
        $balance->spendCredits($balance->available_credits, 'Drain for test');

        $token = $user->createToken('zero')->plainTextToken;

        // Request blocked with 402 insufficient_credits
        $blocked = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/ai/build-circuit', [
                'prompt' => 'Build an Arduino LED blink circuit',
            ]);

        $blocked->assertStatus(402)
            ->assertJsonPath('error', 'insufficient_credits');

        // User creates a Whop checkout and completes top-up
        $checkout = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/credits/checkout', [
                'package_slug' => 'maker-2000',
            ]);

        $checkout->assertStatus(201);
        $orderNumber = $checkout->json('order.order_number');
        $this->assertNotEmpty($orderNumber);

        // Unauthenticated or client-initiated order fulfillment endpoints do not exist (404); only signed webhooks fulfill orders
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson("/api/v1/credits/orders/{$orderNumber}/fulfill")
            ->assertStatus(404);

        // Unsigned Whop webhook is rejected with 401
        $webhookPayload = [
            'action' => 'payment.succeeded',
            'data' => [
                'id' => 'pay_whop_test_991',
                'metadata' => [
                    'order_number' => $orderNumber,
                ],
            ],
        ];
        $rawPayload = json_encode($webhookPayload, JSON_UNESCAPED_SLASHES);

        $this->call('POST', '/api/v1/webhooks/whop', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_WHOP_SIGNATURE' => 'invalid_signature',
        ], $rawPayload)->assertStatus(401);

        // Signed Whop webhook fulfills the order and grants 2300 credits
        $whopService = app(\App\Services\WhopService::class);
        $validSignature = $whopService->generateTestSignature($rawPayload);

        $fulfill = $this->call('POST', '/api/v1/webhooks/whop', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_WHOP_SIGNATURE' => $validSignature,
        ], $rawPayload);

        $fulfill->assertStatus(200)
            ->assertJsonPath('status', 'processed');

        $this->assertEquals(2300.0, (float) $fulfill->json('available_credits'));

        // Now AI request succeeds!
        $unblocked = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/ai/build-circuit', [
                'prompt' => 'Build an Arduino LED blink circuit',
            ]);

        $unblocked->assertStatus(200);
    }

    public function test_missing_ai_model_or_missing_api_key_returns_localized_error_with_zero_credit_deduction(): void
    {
        $user = User::create([
            'name' => 'Localized Error User',
            'email' => 'localized-err@example.com',
            'password' => 'password123',
        ]);
        $balance = $user->ensureInitialCreditGrant();
        $initialCredits = (float) $balance->available_credits;
        $token = $user->createToken('loc-err')->plainTextToken;

        // 1. Deactivate all AI models -> must return 503 ai_model_not_configured in TR & EN without charging credits
        AiModel::query()->update(['is_active' => false]);

        $noModelTr = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('Accept-Language', 'tr')
            ->postJson('/api/v1/ai/agent-turn', [
                'prompt' => 'Bana rastgele bir şey söyle',
                'locale' => 'tr',
            ]);

        $noModelTr->assertStatus(503)
            ->assertJsonPath('error', 'ai_model_not_configured')
            ->assertJsonPath('locale', 'tr');
        $this->assertStringContainsString('aktif bir yapay zeka modeli tanımlı değil', (string) $noModelTr->json('message'));

        $noModelEn = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->withHeader('Accept-Language', 'en')
            ->postJson('/api/v1/ai/agent-turn', [
                'prompt' => 'Tell me something random',
                'locale' => 'en',
            ]);

        $noModelEn->assertStatus(503)
            ->assertJsonPath('error', 'ai_model_not_configured')
            ->assertJsonPath('locale', 'en');
        $this->assertStringContainsString('No active AI model is configured', (string) $noModelEn->json('message'));

        // 2. Re-enable AI models, but enable strict_mode (live environment behavior) with empty API key
        AiModel::query()->update(['is_active' => true]);
        config(['services.ai_gateway.strict_mode' => true]);

        $noKeyTr = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/ai/agent-turn', [
                'prompt' => 'Merhaba',
                'locale' => 'tr',
            ]);

        $noKeyTr->assertStatus(503)
            ->assertJsonPath('error', 'ai_api_key_missing')
            ->assertJsonPath('locale', 'tr');
        $this->assertStringContainsString('API anahtarı', (string) $noKeyTr->json('message'));

        $noKeyEn = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/ai/agent-turn', [
                'prompt' => 'Hello',
                'locale' => 'en',
            ]);

        $noKeyEn->assertStatus(503)
            ->assertJsonPath('error', 'ai_api_key_missing')
            ->assertJsonPath('locale', 'en');
        $this->assertStringContainsString('API key', (string) $noKeyEn->json('message'));

        // Verify zero credits were deducted across all failed calls
        $this->assertEquals($initialCredits, (float) $balance->fresh()->available_credits);
    }
}
