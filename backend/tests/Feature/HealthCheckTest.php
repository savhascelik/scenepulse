<?php

namespace Tests\Feature;

use App\Support\ApiResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_health_endpoint_returns_ok_and_checks_all_services(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'ok',
                'service' => 'circuit-lab-api',
                'services' => [
                    'database' => 'ok',
                    'cache' => 'ok',
                    'storage' => 'ok',
                ],
            ])
            ->assertJsonStructure([
                'status',
                'service',
                'timestamp',
                'services' => [
                    'database',
                    'cache',
                    'storage',
                    'ai_config' => [
                        'default_provider',
                        'nebius_base_url',
                        'cloudflare_gateway_enabled',
                        'nebius_key_configured',
                        'tavily_key_configured',
                    ],
                ],
            ]);
    }

    public function test_api_response_envelope_helper_formats_success_and_error_consistently(): void
    {
        $success = ApiResponse::success(['foo' => 'bar'], 'Done', 201, ['page' => 1]);
        $this->assertSame(201, $success->getStatusCode());
        $this->assertSame([
            'success' => true,
            'message' => 'Done',
            'data' => ['foo' => 'bar'],
            'meta' => ['page' => 1],
        ], $success->getData(true));

        $error = ApiResponse::error('Quota exceeded', 429, 'quota_exceeded', ['limit' => 10]);
        $this->assertSame(429, $error->getStatusCode());
        $this->assertSame([
            'success' => false,
            'code' => 'quota_exceeded',
            'message' => 'Quota exceeded',
            'errors' => ['limit' => 10],
        ], $error->getData(true));
    }
}
