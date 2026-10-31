<?php

namespace Tests\Feature;

use App\Models\AiCreditPackage;
use App\Models\AiModel;
use App\Models\CircuitScenario;
use App\Models\CustomComponent;
use App\Models\DatasheetCache;
use App\Models\StudyGroup;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DatabaseSeederAndDeploymentConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_populates_admin_models_whop_packages_scenarios_and_cached_datasheets(): void
    {
        $this->seed(DatabaseSeeder::class);

        // 1. Admin & Judge accounts exist
        $admin = User::query()->where('email', 'admin@nebius-hackathon.local')->first();
        $this->assertNotNull($admin);
        $this->assertTrue($admin->hasRole('admin'));

        $judge = User::query()->where('email', 'judge@nebius-hackathon.local')->first();
        $this->assertNotNull($judge);
        $this->assertTrue($judge->hasRole('judge'));

        // 2. AiModels & Whop AiCreditPackages are seeded
        $this->assertGreaterThanOrEqual(4, AiModel::query()->count());
        $this->assertGreaterThanOrEqual(3, AiCreditPackage::query()->count());

        // 3. "Açık Donanım Atölyesi" open study group exists
        $group = StudyGroup::query()->where('slug', 'acik-donanim-atolyesi')->first();
        $this->assertNotNull($group);
        $this->assertSame('Açık Donanım Atölyesi', $group->name);
        $this->assertSame('LAB-NEBIUS26', $group->invite_code);

        // 4. 3 shared circuit scenarios exist across Arduino Uno, ESP32, and Jetson Nano
        $this->assertSame(3, CircuitScenario::query()->where('study_group_id', $group->id)->count());

        // 5. 5 pre-cached popular Tavily sensor datasheets exist and serve instant cache hits with 0 HTTP calls
        $expectedParts = ['MPU6050', 'DHT22', 'BH1750', 'BME280', 'INA219'];
        $this->assertSame(5, DatasheetCache::query()->count());
        $this->assertSame(5, CustomComponent::query()->count());

        Http::fake();
        $token = $judge->createToken('judge-seed-test')->plainTextToken;

        foreach ($expectedParts as $partNumber) {
            $res = $this->withHeader('Authorization', 'Bearer ' . $token)
                ->postJson('/api/v1/ai/synthesize-component', [
                    'part_number' => $partNumber,
                    'save_to_catalog' => false,
                ]);

            $res->assertStatus(200)
                ->assertJsonPath('meta.cache_hit', true)
                ->assertJsonPath('schema.part_number', $partNumber);
        }

        // Zero external HTTP calls should have been made for all 5 pre-seeded sensors
        Http::assertNothingSent();
    }

    public function test_dokploy_and_docker_production_configuration_files_are_valid(): void
    {
        $base = base_path();

        $this->assertFileExists($base . '/Dockerfile');
        $this->assertFileExists($base . '/dokploy.json');
        $this->assertFileExists($base . '/docker/start.sh');
        $this->assertFileExists($base . '/docker/nginx.conf');
        $this->assertFileExists($base . '/docker/supervisord.conf');

        $dokploy = json_decode((string) file_get_contents($base . '/dokploy.json'), true);
        $this->assertIsArray($dokploy);
        $this->assertSame('/api/health', $dokploy['deployment']['healthcheck']['path'] ?? null);

        $startSh = (string) file_get_contents($base . '/docker/start.sh');
        $this->assertStringContainsString('php artisan migrate --force', $startSh);
        $this->assertStringContainsString('php artisan db:seed --force', $startSh);
    }
}
