<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleLimitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthAndPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLimitSeeder::class);
    }

    public function test_user_can_register_and_receives_free_role_initial_credits_permissions_and_limits(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'secret1234',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('user.email', 'ada@example.com')
            ->assertJsonPath('user.roles.0', 'free')
            ->assertJsonPath('user.limits.ai_usage.initial_credit_balance', 250)
            ->assertJsonPath('user.limits.groups.max_owned_groups', 5);

        $this->assertEquals(250.0, (float) $response->json('user.credits.available_credits'));

        $permissions = $response->json('user.permissions');
        $this->assertContains('groups.create', $permissions);
        $this->assertContains('scenarios.create', $permissions);
        $this->assertContains('scenarios.fork', $permissions);
        $this->assertContains('ai.generate_circuit', $permissions);
        $this->assertContains('ai.code_mode', $permissions);
        $this->assertContains('ai.search_tools', $permissions);
        $this->assertNotContains('admin.access', $permissions);
    }

    public function test_user_can_login_and_fetch_me_and_logout(): void
    {
        $user = User::create([
            'name' => 'Nikola Tesla',
            'email' => 'tesla@example.com',
            'password' => 'coil12345',
        ]);

        $loginResp = $this->postJson('/api/v1/auth/login', [
            'email' => 'tesla@example.com',
            'password' => 'coil12345',
        ]);

        $loginResp->assertStatus(200)
            ->assertJsonPath('user.email', 'tesla@example.com');

        $token = $loginResp->json('token');
        $this->assertNotEmpty($token);

        $meResp = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/auth/me');

        $meResp->assertStatus(200)
            ->assertJsonPath('user.email', 'tesla@example.com')
            ->assertJsonPath('user.roles.0', 'free');

        $this->assertEquals(250.0, (float) $meResp->json('user.credits.available_credits'));

        $logoutResp = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/auth/logout');

        $logoutResp->assertStatus(200);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_filament_admin_panel_enforces_spatie_admin_role_and_rejects_unregistered_admin_routes(): void
    {
        $this->getJson('/api/v1/admin/overview')->assertStatus(404);
        $this->postJson('/api/v1/admin/role-limits')->assertStatus(404);
        $this->postJson('/api/v1/admin/users/1/credits')->assertStatus(404);

        // Email containing "admin" without Spatie 'admin' role MUST NOT access Filament panel
        $impostor = User::create([
            'name' => 'Non-Admin Engineer',
            'email' => 'engineer-admin@example.com',
            'password' => 'secret1234',
        ]);
        $panel = \Filament\Facades\Filament::getDefaultPanel();
        $this->assertFalse($impostor->canAccessPanel($panel));
        $this->assertTrue($impostor->hasRole('free'));

        // User with Spatie 'admin' role CAN access Filament panel
        $realAdmin = User::create([
            'name' => 'Platform Administrator',
            'email' => 'chief@circuitlab3d.io',
            'password' => 'secret1234',
        ]);
        $realAdmin->syncRoles(['admin']);
        $this->assertTrue($realAdmin->canAccessPanel($panel));

        // Verify HTTP 403 for non-admin vs HTTP 200 for Spatie 'admin' on Filament /admin resources
        $this->actingAs($impostor)->get('/admin')->assertStatus(403);
        $this->actingAs($impostor)->get('/admin/plans')->assertStatus(403);
        $this->actingAs($impostor)->get('/admin/role-limits')->assertStatus(403);
        $this->actingAs($impostor)->get('/admin/circuit-scenarios')->assertStatus(403);

        $this->actingAs($realAdmin)->get('/admin')->assertStatus(200);
        $this->actingAs($realAdmin)->get('/admin/plans')->assertStatus(200);
        $this->actingAs($realAdmin)->get('/admin/subscriptions')->assertStatus(200);
        $this->actingAs($realAdmin)->get('/admin/roles')->assertStatus(200);
        $this->actingAs($realAdmin)->get('/admin/permissions')->assertStatus(200);
        $this->actingAs($realAdmin)->get('/admin/role-limits')->assertStatus(200);
        $this->actingAs($realAdmin)->get('/admin/ai-models')->assertStatus(200);
        $this->actingAs($realAdmin)->get('/admin/ai-credit-packages')->assertStatus(200);
        $this->actingAs($realAdmin)->get('/admin/ai-credit-transactions')->assertStatus(200);
        $this->actingAs($realAdmin)->get('/admin/users')->assertStatus(200);
        $this->actingAs($realAdmin)->get('/admin/circuit-scenarios')->assertStatus(200);
    }

    public function test_user_settings_endpoints_synchronize_with_remote_api_settings_adapter_format(): void
    {
        $user = User::create([
            'name' => 'Grace Hopper',
            'email' => 'grace@example.com',
            'password' => 'cobol1234',
        ]);

        $token = $user->createToken('test-token')->plainTextToken;

        $getResp = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/user/settings');

        $getResp->assertStatus(200)
            ->assertJsonPath('settings.activeBoard', 'arduino_uno');

        $putResp = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/user/settings', [
                'settings' => [
                    'activeBoard' => 'jetson_nano_2gb',
                    'language' => 'en',
                    'showPinLabels' => false,
                ],
            ]);

        $putResp->assertStatus(200)
            ->assertJsonPath('settings.activeBoard', 'jetson_nano_2gb')
            ->assertJsonPath('settings.language', 'en')
            ->assertJsonPath('settings.showPinLabels', false);

        $aliasResp = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/user/settings');

        $aliasResp->assertStatus(200)
            ->assertJsonPath('settings.activeBoard', 'jetson_nano_2gb');
    }

    public function test_unauthenticated_requests_to_protected_endpoints_are_rejected(): void
    {
        $this->getJson('/api/v1/auth/me')->assertStatus(401);
        $this->getJson('/api/v1/user/settings')->assertStatus(401);
        $this->putJson('/api/v1/user/settings', ['settings' => []])->assertStatus(401);
    }

    public function test_role_limit_quota_enforcement_blocks_free_user_at_limit_and_allows_pro_unlimited(): void
    {
        $user = User::create([
            'name' => 'Quota Tester',
            'email' => 'quota@example.com',
            'password' => 'secret1234',
        ]);
        $token = $user->createToken('quota-token')->plainTextToken;

        // Set free role max_saved_scenarios to 3 via RoleLimit model (verifying automatic Cache::forget)
        $freeRole = \Spatie\Permission\Models\Role::where('name', 'free')->firstOrFail();
        \App\Models\RoleLimit::updateOrCreate(
            [
                'role_id' => $freeRole->id,
                'resource_type' => \App\Models\RoleLimit::RESOURCE_SCENARIOS,
                'limit_key' => 'max_saved_scenarios',
            ],
            [
                'limit_value' => 3,
                'description' => 'Test 3 scenarios cap for Free tier',
            ]
        );

        // Verify /api/v1/limits/overview reflects limit = 3
        $overviewResp = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/limits/overview');
        $overviewResp->assertStatus(200)
            ->assertJsonPath('data.role', 'free')
            ->assertJsonPath('data.resources.scenarios.limit', 3)
            ->assertJsonPath('data.resources.scenarios.used', 0)
            ->assertJsonPath('data.resources.scenarios.can_create', true);

        // Create 3 scenarios (all 3 should succeed with 201)
        for ($i = 1; $i <= 3; $i++) {
            $this->withHeader('Authorization', 'Bearer ' . $token)
                ->postJson('/api/v1/scenarios', [
                    'title' => "Circuit Workspace #{$i}",
                    'board_type' => 'board.arduino_uno',
                ])
                ->assertStatus(201);
        }

        // Verify can-create endpoint now reports allowed: false, reason: total_limit_reached
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/limits/scenarios/can-create')
            ->assertStatus(200)
            ->assertJsonPath('data.allowed', false)
            ->assertJsonPath('data.reason', 'total_limit_reached')
            ->assertJsonPath('data.used', 3)
            ->assertJsonPath('data.limit', 3);

        // Attempting 4th scenario as 'free' user MUST return 403 total_limit_reached
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/scenarios', [
                'title' => 'Circuit Workspace #4 (Blocked)',
                'board_type' => 'board.arduino_uno',
            ])
            ->assertStatus(403)
            ->assertJsonPath('code', 'total_limit_reached')
            ->assertJsonPath('errors.limit', 3)
            ->assertJsonPath('errors.current', 3);

        // Upgrade user to 'pro' role (unlimited = -1)
        $user->syncRoles(['pro']);

        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/limits/scenarios/can-create')
            ->assertStatus(200)
            ->assertJsonPath('data.allowed', true)
            ->assertJsonPath('data.unlimited', true)
            ->assertJsonPath('data.limit', -1);

        // 4th scenario now succeeds with 201
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/scenarios', [
                'title' => 'Circuit Workspace #4 (Pro Unlimited)',
                'board_type' => 'board.arduino_uno',
            ])
            ->assertStatus(201);
    }

    public function test_authenticated_user_can_update_profile_and_change_password(): void
    {
        $registerResponse = $this->postJson('/api/v1/auth/register', [
            'name' => 'İlk İsim',
            'email' => 'profil@circuitlab3d.dev',
            'password' => 'eskiSifre123',
        ])->assertStatus(201);

        $token = $registerResponse->json('token');

        // 1. Update profile name & email
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/auth/profile', [
                'name' => 'Güncellenmiş Mühendis',
                'email' => 'yeni.profil@circuitlab3d.dev',
            ])
            ->assertStatus(200)
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('user.name', 'Güncellenmiş Mühendis')
            ->assertJsonPath('user.email', 'yeni.profil@circuitlab3d.dev');

        // 2. Wrong current password must be rejected with 422
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/auth/password', [
                'current_password' => 'yanlisSifre',
                'password' => 'yeniGucluSifre456',
                'password_confirmation' => 'yeniGucluSifre456',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);

        // 3. Valid password change succeeds
        $this->withHeader('Authorization', 'Bearer ' . $token)
            ->putJson('/api/v1/auth/password', [
                'current_password' => 'eskiSifre123',
                'password' => 'yeniGucluSifre456',
                'password_confirmation' => 'yeniGucluSifre456',
            ])
            ->assertStatus(200)
            ->assertJsonPath('status', 'ok');

        // 4. Can login with new email and new password
        $this->postJson('/api/v1/auth/login', [
            'email' => 'yeni.profil@circuitlab3d.dev',
            'password' => 'yeniGucluSifre456',
        ])->assertStatus(200);
    }

    public function test_unverified_role_cannot_bypass_rbac_on_ai_scenarios_groups_components_or_credits_and_public_endpoints_hide_sensitive_fields(): void
    {
        $this->seed(\Database\Seeders\AiModelSeeder::class);

        $owner = User::create([
            'name' => 'Owner Engineer',
            'email' => 'owner@circuitlab3d.dev',
            'password' => 'secret1234',
        ]);
        $ownerToken = $owner->createToken('owner-token')->plainTextToken;

        // Owner creates a public scenario and a private custom component
        $scenarioResp = $this->withHeader('Authorization', 'Bearer ' . $ownerToken)
            ->postJson('/api/v1/scenarios', [
                'title' => 'Public Test Circuit',
                'board_type' => 'board.arduino_uno',
                'visibility' => 'public',
            ])
            ->assertStatus(201);
        $scenarioSlug = $scenarioResp->json('data.scenario.slug');
        $this->assertNotEmpty($scenarioSlug);

        $compResp = $this->withHeader('Authorization', 'Bearer ' . $ownerToken)
            ->postJson('/api/v1/components', [
                'name' => 'Secret Sensor',
                'part_number' => 'SECRET-100',
                'category' => 'sensor',
                'visibility' => 'private',
                'pinout' => [['id' => 'VCC', 'type' => 'power']],
                'electrical_specs' => ['voltage' => 3.3],
                'internal_dipoles' => [['from' => 'VCC', 'to' => 'GND', 'resistance' => 10000]],
                'mesh_recipe' => ['shape' => 'box'],
            ])
            ->assertStatus(201);
        $privateCompSlug = $compResp->json('data.component.slug');
        $this->assertNotEmpty($privateCompSlug);

        // Unverified user has zero write/AI permissions
        $unverified = User::create([
            'name' => 'Unverified Student',
            'email' => 'unverified@circuitlab3d.dev',
            'password' => 'secret1234',
        ]);
        $unverified->syncRoles(['unverified']);
        $unverifiedToken = $unverified->createToken('unverified-token')->plainTextToken;

        // 1. Unverified user cannot call AI endpoints
        $this->withHeader('Authorization', 'Bearer ' . $unverifiedToken)
            ->postJson('/api/v1/ai/agent-turn', [
                'messages' => [['role' => 'user', 'content' => 'Build LED']],
            ])->assertStatus(403);

        $this->withHeader('Authorization', 'Bearer ' . $unverifiedToken)
            ->postJson('/api/v1/ai/build-circuit', [
                'prompt' => 'Build LED circuit',
            ])->assertStatus(403);

        $this->withHeader('Authorization', 'Bearer ' . $unverifiedToken)
            ->postJson('/api/v1/ai/synthesize-component', [
                'part_name' => 'ADS1115',
            ])->assertStatus(403);

        $this->withHeader('Authorization', 'Bearer ' . $unverifiedToken)
            ->postJson('/api/v1/ai/diagnose-circuit', [
                'fault_log' => ['error' => 'short_circuit'],
            ])->assertStatus(403);

        // 2. Unverified user cannot create scenarios, groups, components, or purchase credits
        $this->withHeader('Authorization', 'Bearer ' . $unverifiedToken)
            ->postJson('/api/v1/scenarios', [
                'title' => 'Blocked Scenario',
            ])->assertStatus(403);

        $this->withHeader('Authorization', 'Bearer ' . $unverifiedToken)
            ->postJson('/api/v1/groups', [
                'name' => 'Blocked Group',
            ])->assertStatus(403);

        $this->withHeader('Authorization', 'Bearer ' . $unverifiedToken)
            ->postJson('/api/v1/components', [
                'name' => 'Blocked Component',
                'part_number' => 'BLK-1',
                'pins' => [['id' => 'VCC']],
                'mesh_spec' => ['shape' => 'box'],
            ])->assertStatus(403);

        $this->withHeader('Authorization', 'Bearer ' . $unverifiedToken)
            ->postJson('/api/v1/credits/checkout', [
                'package_slug' => 'starter-pack',
            ])->assertStatus(403);

        // 3. Another user cannot read private custom component by slug
        $this->withHeader('Authorization', 'Bearer ' . $unverifiedToken)
            ->getJson('/api/v1/components/' . $privateCompSlug)
            ->assertStatus(403);

        // Owner CAN read their own private custom component
        $this->withHeader('Authorization', 'Bearer ' . $ownerToken)
            ->getJson('/api/v1/components/' . $privateCompSlug)
            ->assertStatus(200);

        // 4. Public /api/v1/ai/models hides internal fields (api_key_env, base_url, costs)
        $modelsResp = $this->getJson('/api/v1/ai/models')->assertStatus(200);
        $firstModel = $modelsResp->json('data.models.0') ?? $modelsResp->json('models.0');
        $this->assertNotEmpty($firstModel);
        $this->assertArrayNotHasKey('api_key_env', $firstModel);
        $this->assertArrayNotHasKey('base_url', $firstModel);
        $this->assertArrayNotHasKey('input_cost_per_1m', $firstModel);
        $this->assertArrayNotHasKey('output_cost_per_1m', $firstModel);
        $this->assertArrayHasKey('has_server_key', $firstModel);

        // 5. Public scenario listing does NOT expose owner email
        $publicScenarios = $this->getJson('/api/v1/scenarios')->assertStatus(200);
        $scenarioRows = $publicScenarios->json('data.scenarios') ?? $publicScenarios->json('data') ?? [];
        $foundScenario = collect($scenarioRows)->firstWhere('slug', $scenarioSlug);
        $this->assertNotNull($foundScenario);
        $this->assertArrayNotHasKey('email', $foundScenario['owner'] ?? []);
    }
}
