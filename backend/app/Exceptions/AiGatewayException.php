<?php

namespace App\Exceptions;

use App\Models\AiModel;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class AiGatewayException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        public readonly int $httpStatus = 503,
        public readonly string $locale = 'tr',
        public readonly array $meta = []
    ) {
        parent::__construct($message, $httpStatus);
    }

    public static function resolveLocale(?string $preferred = null): string
    {
        $raw = strtolower(trim((string) ($preferred ?: request()?->input('locale') ?: request()?->header('Accept-Language', 'tr'))));

        return str_starts_with($raw, 'en') ? 'en' : 'tr';
    }

    public static function modelNotConfigured(?string $locale = null): self
    {
        $lang = self::resolveLocale($locale);
        $message = $lang === 'en'
            ? 'No active AI model is configured on the system. Please add and enable an AI model in the admin panel.'
            : 'Sistemde aktif bir yapay zeka modeli tanımlı değil. Lütfen yönetim panelinden bir AI modeli ekleyin ve etkinleştirin.';

        return new self(
            errorCode: 'ai_model_not_configured',
            message: $message,
            httpStatus: 503,
            locale: $lang
        );
    }

    public static function apiKeyMissing(?AiModel $model, string $envKeyName, ?string $locale = null): self
    {
        $lang = self::resolveLocale($locale);
        $modelName = $model?->display_name ?? $model?->model_id ?? 'AI Model';
        $message = $lang === 'en'
            ? "The API key ({$envKeyName}) for the active AI model ({$modelName}) is not configured. Please set the API key in the server configuration."
            : "Aktif yapay zeka modeli ({$modelName}) için API anahtarı ({$envKeyName}) tanımlı değil. Lütfen sunucu veya yönetim paneli ayarlarından API anahtarını yapılandırın.";

        return new self(
            errorCode: 'ai_api_key_missing',
            message: $message,
            httpStatus: 503,
            locale: $lang,
            meta: [
                'model_id' => $model?->model_id,
                'display_name' => $model?->display_name,
                'api_key_env' => $envKeyName,
            ]
        );
    }

    public static function providerError(?AiModel $model, ?string $locale = null, ?string $detail = null): self
    {
        $lang = self::resolveLocale($locale);
        $modelName = $model?->display_name ?? $model?->model_id ?? 'AI Model';
        $message = $lang === 'en'
            ? "Failed to receive a valid response from the AI provider ({$modelName}). Please check the model connection settings and API key."
            : "Yapay zeka sağlayıcısından ({$modelName}) geçerli bir yanıt alınamadı. Lütfen model bağlantı ayarlarını ve API anahtarını kontrol edin.";

        return new self(
            errorCode: 'ai_provider_error',
            message: $message,
            httpStatus: 502,
            locale: $lang,
            meta: array_filter([
                'model_id' => $model?->model_id,
                'display_name' => $model?->display_name,
                'detail' => $detail,
            ])
        );
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'error' => $this->errorCode,
            'message' => $this->getMessage(),
            'locale' => $this->locale,
            'meta' => $this->meta,
        ], $this->httpStatus);
    }
}
