<?php

namespace App\Services;

use App\Exceptions\AiGatewayException;
use App\Models\AiModel;
use App\Models\AiUsageLog;
use App\Models\User;
use Illuminate\Support\Facades\Http;

class AiGatewayService
{
    public function __construct(
        private readonly AiCreditService $creditService
    ) {}

    /**
     * Resolve the active AI model for a given task role, or override via model_id / model_slug.
     */
    public function resolveModel(string $taskRole, ?int $preferredModelId = null, ?string $preferredModelSlug = null): ?AiModel
    {
        if ($preferredModelId) {
            $found = AiModel::where('id', $preferredModelId)->where('is_active', true)->first();
            if ($found) {
                return $found;
            }
        }

        if ($preferredModelSlug !== null && trim($preferredModelSlug) !== '') {
            $slug = trim($preferredModelSlug);
            $byExactId = AiModel::where('is_active', true)
                ->where('model_id', $slug)
                ->first();
            if ($byExactId) {
                return $byExactId;
            }
        }

        return AiModel::getDefaultForTask($taskRole);
    }

    /**
     * Resolve the configured API key for a given AiModel.
     */
    public function resolveApiKey(?AiModel $dbModel, ?string $byokKey = null): string
    {
        if ($byokKey !== null && trim($byokKey) !== '') {
            return trim($byokKey);
        }

        $envKeyName = trim((string) ($dbModel?->api_key_env ?? 'NEBIUS_API_KEY'));
        $apiKey = (string) env($envKeyName, '');

        if ($apiKey === '' && $envKeyName !== '') {
            $apiKey = (string) config("ai.env_keys.{$envKeyName}", '');
        }

        if ($apiKey === '' && $envKeyName === 'NEBIUS_API_KEY') {
            $apiKey = (string) (config('ai.nebius.api_key', '') ?: config('services.nebius.api_key', ''));
        }

        // If the admin pasted a raw key directly into api_key_env instead of an ENV_VAR_NAME
        if ($apiKey === '' && $envKeyName !== '' && ! preg_match('/^[A-Z][A-Z0-9_]+$/', $envKeyName)) {
            $apiKey = $envKeyName;
        }

        return trim($apiKey);
    }

    /**
     * Execute an OpenAI-compatible chat completion and deduct AI credits via AiCreditService.
     *
     * @return array{parsed: array<string, mixed>, raw_text: string, model_used: string, provider_slug: string, latency_ms: int, used_byok: bool, fallback_used: bool, credits_charged: float, remaining_credits: float, input_tokens: int, output_tokens: int}
     */
    public function chatJson(
        ?User $user,
        string $taskRole,
        string $systemPrompt,
        string $userPrompt,
        callable $deterministicFallback,
        ?int $preferredModelId = null,
        ?string $byokKey = null,
        ?string $byokBaseUrl = null,
        ?string $byokModelId = null
    ): array {
        $startTime = microtime(true);
        $dbModel = $this->resolveModel($taskRole, $preferredModelId);

        if ($dbModel === null && ($byokModelId === null || trim($byokModelId) === '')) {
            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);
            $this->logUsage(
                $user,
                null,
                $taskRole,
                'none',
                'unconfigured',
                0,
                0,
                $latencyMs,
                false,
                false,
                'ai_model_not_configured'
            );

            throw AiGatewayException::modelNotConfigured();
        }

        $providerSlug = $dbModel?->provider_slug ?? 'nebius';
        $envNebiusBaseUrl = $providerSlug === 'nebius'
            ? trim((string) (env('NEBIUS_BASE_URL', '') ?: config('ai.nebius.base_url', '')))
            : '';
        $baseUrl = rtrim(
            $byokBaseUrl
                ?: ($envNebiusBaseUrl !== '' ? $envNebiusBaseUrl : ($dbModel?->base_url ?? config('services.nebius.base_url', 'https://api.tokenfactory.nebius.com/v1'))),
            '/'
        );
        if ($providerSlug === 'nebius' && str_contains($baseUrl, 'api.studio.nebius.com')) {
            $baseUrl = 'https://api.tokenfactory.nebius.com/v1';
        }

        $modelId = $byokModelId ?: ($dbModel?->model_id ?? 'nvidia/nemotron-3-super-120b-a12b');
        if ($providerSlug === 'nebius' && ! $byokModelId && str_starts_with($modelId, 'meta-llama/Meta-Llama-3.1-')) {
            $modelId = str_contains($modelId, '8B')
                ? 'Qwen/Qwen3-30B-A3B-Instruct-2507'
                : 'nvidia/nemotron-3-super-120b-a12b';
        }
        $envKeyName = trim((string) ($dbModel?->api_key_env ?? 'NEBIUS_API_KEY'));
        $apiKey = $this->resolveApiKey($dbModel, $byokKey);

        $usedByok = $byokKey !== null && trim($byokKey) !== '';
        $promptTokens = max(120, (int) ceil((strlen($systemPrompt) + strlen($userPrompt)) / 4));
        $completionTokens = 0;

        $hasValidApiKey = $apiKey !== '' && ! str_starts_with($apiKey, 'your_');
        $strictMode = ! app()->environment('testing') || (bool) config('services.ai_gateway.strict_mode', false);

        if (! $hasValidApiKey && $strictMode) {
            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);
            $this->logUsage(
                $user,
                $dbModel,
                $taskRole,
                $providerSlug,
                $modelId,
                0,
                0,
                $latencyMs,
                $usedByok,
                false,
                'ai_api_key_missing'
            );

            throw AiGatewayException::apiKeyMissing($dbModel, $envKeyName);
        }

        if ($hasValidApiKey) {
            try {
                if (function_exists('set_time_limit')) {
                    @set_time_limit(120);
                }

                $extraPayload = is_array($dbModel?->default_extra_payload) ? $dbModel->default_extra_payload : [];
                $payload = array_merge([
                    'max_tokens' => 4096,
                ], $extraPayload, [
                    'model' => $modelId,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                    'temperature' => (float) ($dbModel?->default_temperature ?? 0.2),
                    'response_format' => ['type' => 'json_object'],
                ]);

                $timeoutSeconds = (int) config('services.ai_gateway.timeout', 90);
                $response = Http::withToken($apiKey)
                    ->connectTimeout(15)
                    ->timeout(max(60, $timeoutSeconds))
                    ->post("{$baseUrl}/chat/completions", $payload);

                if (! $response->successful()) {
                    $latencyMs = (int) round((microtime(true) - $startTime) * 1000);
                    $this->logUsage(
                        $user,
                        $dbModel,
                        $taskRole,
                        $providerSlug,
                        $modelId,
                        $promptTokens,
                        0,
                        $latencyMs,
                        $usedByok,
                        false,
                        'ai_provider_error'
                    );

                    throw AiGatewayException::providerError(
                        $dbModel,
                        null,
                        'HTTP ' . $response->status()
                    );
                }

                $body = $response->json();
                $messageObj = $body['choices'][0]['message'] ?? [];
                $rawText = (string) ($messageObj['content'] ?? '');
                $parsed = $this->extractJsonObject($rawText);

                if (empty($parsed) && ! empty($messageObj['reasoning_content'])) {
                    $parsed = $this->extractJsonObject((string) $messageObj['reasoning_content']);
                }

                if (empty($parsed)) {
                    $latencyMs = (int) round((microtime(true) - $startTime) * 1000);
                    $this->logUsage(
                        $user,
                        $dbModel,
                        $taskRole,
                        $providerSlug,
                        $modelId,
                        $promptTokens,
                        0,
                        $latencyMs,
                        $usedByok,
                        false,
                        'ai_provider_invalid_json'
                    );

                    throw AiGatewayException::providerError(
                        $dbModel,
                        null,
                        'Empty or invalid JSON payload from provider'
                    );
                }

                $latencyMs = (int) round((microtime(true) - $startTime) * 1000);
                $promptTokens = (int) ($body['usage']['prompt_tokens'] ?? $promptTokens);
                $completionTokens = (int) ($body['usage']['completion_tokens'] ?? max(180, (int) ceil(strlen($rawText) / 4)));

                $this->logUsage(
                    $user,
                    $dbModel,
                    $taskRole,
                    $providerSlug,
                    $modelId,
                    $promptTokens,
                    $completionTokens,
                    $latencyMs,
                    $usedByok,
                    false,
                    'success'
                );

                $creditAccounting = $user
                    ? $this->creditService->recordUsageAndCharge($user, $dbModel, $taskRole, $promptTokens, $completionTokens, $latencyMs)
                    : ['credits_charged' => 0.0, 'remaining_credits' => 0.0];

                return [
                    'parsed' => $parsed,
                    'raw_text' => $rawText,
                    'model_used' => $modelId,
                    'provider_slug' => $providerSlug,
                    'latency_ms' => $latencyMs,
                    'used_byok' => $usedByok,
                    'fallback_used' => false,
                    'credits_charged' => $creditAccounting['credits_charged'],
                    'remaining_credits' => $creditAccounting['remaining_credits'],
                    'input_tokens' => $promptTokens,
                    'output_tokens' => $completionTokens,
                ];
            } catch (AiGatewayException $e) {
                throw $e;
            } catch (\Throwable $e) {
                $latencyMs = (int) round((microtime(true) - $startTime) * 1000);
                $this->logUsage(
                    $user,
                    $dbModel,
                    $taskRole,
                    $providerSlug,
                    $modelId,
                    $promptTokens,
                    0,
                    $latencyMs,
                    $usedByok,
                    false,
                    'ai_provider_exception'
                );

                throw AiGatewayException::providerError($dbModel, null, $e->getMessage());
            }
        }

        $fallbackData = $deterministicFallback();
        $rawFallback = json_encode($fallbackData, JSON_UNESCAPED_UNICODE) ?: '{}';
        $completionTokens = max(220, (int) ceil(strlen($rawFallback) / 4));
        $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

        $this->logUsage(
            $user,
            $dbModel,
            $taskRole,
            $providerSlug,
            $modelId . ' (deterministic-engine)',
            $promptTokens,
            $completionTokens,
            $latencyMs,
            false,
            false,
            'deterministic_fallback'
        );

        $creditAccounting = $user
            ? $this->creditService->recordUsageAndCharge($user, $dbModel, $taskRole, $promptTokens, $completionTokens, $latencyMs, ['engine' => 'deterministic_fallback'])
            : ['credits_charged' => 0.0, 'remaining_credits' => 0.0];

        return [
            'parsed' => $fallbackData,
            'raw_text' => $rawFallback,
            'model_used' => $modelId,
            'provider_slug' => $providerSlug,
            'latency_ms' => $latencyMs,
            'used_byok' => $usedByok,
            'fallback_used' => true,
            'credits_charged' => $creditAccounting['credits_charged'],
            'remaining_credits' => $creditAccounting['remaining_credits'],
            'input_tokens' => $promptTokens,
            'output_tokens' => $completionTokens,
        ];
    }

    /**
     * Extract JSON object cleanly even if wrapped in markdown code fences.
     *
     * @return array<string, mixed>
     */
    private function extractJsonObject(string $rawText): array
    {
        $trimmed = trim($rawText);
        $trimmed = preg_replace('/<think>.*?<\/think>/is', '', $trimmed) ?? $trimmed;
        $trimmed = trim($trimmed);

        $decoded = json_decode($trimmed, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/is', $trimmed, $matches)) {
            $decoded = json_decode($matches[1], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $firstBrace = strpos($trimmed, '{');
        $lastBrace = strrpos($trimmed, '}');
        if ($firstBrace !== false && $lastBrace !== false && $lastBrace > $firstBrace) {
            $candidate = substr($trimmed, $firstBrace, $lastBrace - $firstBrace + 1);
            $decoded = json_decode($candidate, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    private function logUsage(
        ?User $user,
        ?AiModel $dbModel,
        string $taskRole,
        string $providerSlug,
        string $modelId,
        int $promptTokens,
        int $completionTokens,
        int $latencyMs,
        bool $usedByok,
        bool $cacheHit,
        string $status
    ): void {
        AiUsageLog::create([
            'user_id' => $user?->id,
            'ai_model_id' => $dbModel?->id,
            'task_role' => $taskRole,
            'provider_slug' => $providerSlug,
            'model_id' => $modelId,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'latency_ms' => $latencyMs,
            'used_byok' => $usedByok,
            'cache_hit' => $cacheHit,
            'status' => $status,
        ]);
    }
}
