<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleLimitSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupAndScenarioSharingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->seed(RoleLimitSeeder::class);
    }

    public function test_any_authenticated_user_can_create_study_group_and_others_can_join_via_invite_code(): void
    {
        $alice = User::create([
            'name' => 'Alice Maker',
            'email' => 'alice@example.com',
            'password' => 'password123',
        ]);
        $bob = User::create([
            'name' => 'Bob Builder',
            'email' => 'bob@example.com',
            'password' => 'password123',
        ]);

        $aliceToken = $alice->createToken('alice')->plainTextToken;
        $bobToken = $bob->createToken('bob')->plainTextToken;

        // 1. Alice (free role, not restricted to 'teacher') creates a Study Group
        $createGroup = $this->withHeader('Authorization', 'Bearer ' . $aliceToken)
            ->postJson('/api/v1/groups', [
                'name' => 'Robotics & Jetson Club',
                'description' => 'Open hardware study group for everyone',
                'is_public' => false,
            ]);

        $createGroup->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.group.name', 'Robotics & Jetson Club');

        $groupId = $createGroup->json('data.group.id');
        $groupSlug = $createGroup->json('data.group.slug');
        $inviteCode = $createGroup->json('data.group.invite_code');
        $this->assertStringStartsWith('LAB-', $inviteCode);

        // 2. Bob cannot view private group before joining
        $this->withHeader('Authorization', 'Bearer ' . $bobToken)
            ->getJson("/api/v1/groups/{$groupSlug}")
            ->assertStatus(403)
            ->assertJsonPath('code', 'group_private');

        // 3. Bob joins using the invite_code
        $joinResp = $this->withHeader('Authorization', 'Bearer ' . $bobToken)
            ->postJson('/api/v1/groups/join', [
                'invite_code' => $inviteCode,
            ]);

        $joinResp->assertStatus(200)
            ->assertJsonPath('data.group.id', $groupId)
            ->assertJsonPath('data.membership.member_role', 'member');

        // 4. Now Bob can view the group
        $this->withHeader('Authorization', 'Bearer ' . $bobToken)
            ->getJson("/api/v1/groups/{$groupSlug}")
            ->assertStatus(200)
            ->assertJsonPath('data.is_member', true);
    }

    public function test_private_scenarios_are_protected_while_group_and_public_scenarios_can_be_viewed_and_forked(): void
    {
        $alice = User::create([
            'name' => 'Alice Maker',
            'email' => 'alice2@example.com',
            'password' => 'password123',
        ]);
        $bob = User::create([
            'name' => 'Bob Builder',
            'email' => 'bob2@example.com',
            'password' => 'password123',
        ]);
        $charlie = User::create([
            'name' => 'Charlie Outsider',
            'email' => 'charlie@example.com',
            'password' => 'password123',
        ]);

        $aliceToken = $alice->createToken('alice')->plainTextToken;
        $bobToken = $bob->createToken('bob')->plainTextToken;
        $charlieToken = $charlie->createToken('charlie')->plainTextToken;

        // Alice creates a study group and Bob joins
        $groupResp = $this->withHeader('Authorization', 'Bearer ' . $aliceToken)
            ->postJson('/api/v1/groups', ['name' => 'Embedded AI Lab']);
        $groupId = $groupResp->json('data.group.id');
        $inviteCode = $groupResp->json('data.group.invite_code');

        $this->withHeader('Authorization', 'Bearer ' . $bobToken)
            ->postJson('/api/v1/groups/join', ['invite_code' => $inviteCode])
            ->assertStatus(200);

        // 1. Alice creates a PRIVATE scenario
        $privateResp = $this->withHeader('Authorization', 'Bearer ' . $aliceToken)
            ->postJson('/api/v1/scenarios', [
                'title' => 'Secret Prototype Circuit',
                'board_type' => 'arduino_uno',
                'visibility' => 'private',
                'scenario_data' => [
                    'schema_version' => 2,
                    'boards' => [['id' => 'uno-1', 'type' => 'arduino_uno']],
                    'components' => [['id' => 'led-1', 'type' => 'led_red']],
                    'connections' => [['from' => 'uno-1:D13', 'to' => 'led-1:A']],
                    'code' => 'void setup(){ pinMode(13, OUTPUT); } void loop(){ digitalWrite(13, HIGH); }',
                ],
            ]);

        $privateResp->assertStatus(201);
        $privateSlug = $privateResp->json('data.scenario.slug');

        // Bob (another user) MUST NOT be able to view or fork Alice's private scenario
        $this->withHeader('Authorization', 'Bearer ' . $bobToken)
            ->getJson("/api/v1/scenarios/{$privateSlug}")
            ->assertStatus(403)
            ->assertJsonPath('code', 'scenario_private');

        $this->withHeader('Authorization', 'Bearer ' . $bobToken)
            ->postJson("/api/v1/scenarios/{$privateSlug}/fork")
            ->assertStatus(403);

        // 2. Alice creates a GROUP-shared scenario
        $groupScenarioResp = $this->withHeader('Authorization', 'Bearer ' . $aliceToken)
            ->postJson('/api/v1/scenarios', [
                'title' => 'Jetson Nano I2C Sensor Rig',
                'board_type' => 'jetson_nano_2gb',
                'visibility' => 'group',
                'study_group_id' => $groupId,
                'scenario_data' => [
                    'schema_version' => 2,
                    'boards' => [['id' => 'jetson-1', 'type' => 'jetson_nano_2gb']],
                    'components' => [['id' => 'mpu-1', 'type' => 'MPU6050']],
                    'connections' => [['from' => 'jetson-1:SDA', 'to' => 'mpu-1:SDA']],
                    'code' => 'import smbus2',
                ],
            ]);

        $groupScenarioResp->assertStatus(201);
        $groupScenarioSlug = $groupScenarioResp->json('data.scenario.slug');
        $groupScenarioId = $groupScenarioResp->json('data.scenario.id');

        // Charlie (not in group) cannot view or fork the group scenario
        $this->withHeader('Authorization', 'Bearer ' . $charlieToken)
            ->getJson("/api/v1/scenarios/{$groupScenarioSlug}")
            ->assertStatus(403)
            ->assertJsonPath('code', 'scenario_group_restricted');

        // Bob (group member) CAN view and FORK the group scenario into his own workspace
        $this->withHeader('Authorization', 'Bearer ' . $bobToken)
            ->getJson("/api/v1/scenarios/{$groupScenarioSlug}")
            ->assertStatus(200)
            ->assertJsonPath('data.scenario.title', 'Jetson Nano I2C Sensor Rig');

        $forkResp = $this->withHeader('Authorization', 'Bearer ' . $bobToken)
            ->postJson("/api/v1/scenarios/{$groupScenarioSlug}/fork", [
                'title' => 'Bob Jetson Remix',
            ]);

        $forkResp->assertStatus(201)
            ->assertJsonPath('data.scenario.title', 'Bob Jetson Remix')
            ->assertJsonPath('data.scenario.user_id', $bob->id)
            ->assertJsonPath('data.scenario.remixed_from_id', $groupScenarioId)
            ->assertJsonPath('data.scenario.visibility', 'private');
    }

    public function test_custom_3d_component_can_be_published_and_listed_in_group_and_community(): void
    {
        $user = User::create([
            'name' => 'Sensor Architect',
            'email' => 'sensor@example.com',
            'password' => 'password123',
        ]);
        $token = $user->createToken('sensor')->plainTextToken;

        $compResp = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/components', [
                'part_number' => 'BME280',
                'name' => 'Bosch BME280 Environmental Sensor',
                'category' => 'sensor',
                'visibility' => 'public',
                'pinout' => [
                    ['name' => 'VCC', 'type' => 'power', 'voltage' => 3.3],
                    ['name' => 'GND', 'type' => 'ground'],
                    ['name' => 'SCL', 'type' => 'i2c_clock'],
                    ['name' => 'SDA', 'type' => 'i2c_data'],
                ],
                'electrical_specs' => ['vcc_min' => 1.71, 'vcc_max' => 3.6, 'interface' => 'I2C'],
                'internal_dipoles' => [['between' => ['VCC', 'GND'], 'resistance_ohms' => 100000]],
                'mesh_recipe' => ['pcb_color' => '#7c2d92', 'width_mm' => 15, 'length_mm' => 12],
            ]);

        $compResp->assertStatus(201)
            ->assertJsonPath('data.component.part_number', 'BME280');

        $this->getJson('/api/v1/components')
            ->assertStatus(200)
            ->assertJsonPath('data.components.0.part_number', 'BME280');
    }
}
