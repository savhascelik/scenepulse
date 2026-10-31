<?php

namespace App\Services;

use App\Models\AiCreditOrder;
use App\Models\AiCreditPackage;
use App\Models\User;

class WhopService
{
    private string $webhookSecret;
    private string $frontendUrl;

    public function __construct()
    {
        $configuredSecret = trim((string) config('services.whop.webhook_secret', ''));
        if ($configuredSecret === '' && ! app()->environment('production')) {
            $configuredSecret = 'whop_test_secret_2026';
        }
        $this->webhookSecret = $configuredSecret;
        $this->frontendUrl = rtrim((string) config('app.frontend_url', env('FRONTEND_URL', 'http://127.0.0.1:5173')), '/');
    }

    /**
     * Build a Whop checkout URL for an AI credit package purchase.
     */
    public function createCreditCheckoutUrl(AiCreditPackage $package, User $user, AiCreditOrder $order): string
    {
        $checkoutTargetId = $package->whop_plan_id ?: ($package->whop_product_id ?: "plan_{$package->slug}");
        $baseCheckout = $package->whop_checkout_url
            ?: "https://whop.com/checkout/{$checkoutTargetId}/";

        $queryParams = http_build_query([
            'email' => $user->email,
            'd2c' => 'true',
            'redirect_url' => "{$this->frontendUrl}/workbench?whop_order={$order->order_number}&payment=success",
            'metadata' => [
                'type' => 'ai_credit_topup',
                'order_number' => $order->order_number,
                'order_id' => $order->id,
                'user_id' => $user->id,
                'user_email' => $user->email,
                'package_id' => $package->id,
                'package_slug' => $package->slug,
                'credits' => (float) $package->credits,
                'bonus_credits' => (float) $package->bonus_credits,
            ],
        ]);

        $separator = str_contains($baseCheckout, '?') ? '&' : '?';

        return "{$baseCheckout}{$separator}{$queryParams}";
    }

    /**
     * Build Whop Elements (Beta) embedded modal checkout configuration (`checkout_session`).
     * Follows https://docs.whop.com/elements/beta/getting-started
     */
    public function buildEmbeddedCheckoutConfig(AiCreditPackage $package, User $user, AiCreditOrder $order): array
    {
        $planId = $package->whop_plan_id ?: ($package->whop_product_id ?: "plan_{$package->slug}");
        $returnUrl = "{$this->frontendUrl}/workbench?checkout_order={$order->order_number}&payment=success";
        $webhookUrl = rtrim((string) config('app.url', $this->frontendUrl), '/') . '/api/v1/webhooks/whop';

        return [
            'plan_id' => $planId,
            'product_id' => $package->whop_product_id,
            'environment' => (string) config('services.whop.environment', 'production'),
            'buyer_email' => (string) $user->email,
            'return_url' => $returnUrl,
            'webhook_url' => $webhookUrl,
            'metadata' => [
                'type' => 'ai_credit_topup',
                'order_id' => (string) $order->id,
                'order_number' => (string) $order->order_number,
                'user_id' => (string) $user->id,
                'user_email' => (string) $user->email,
                'package_id' => (string) $package->id,
                'package_slug' => (string) $package->slug,
                'credits' => (string) ((float) $package->credits),
                'bonus_credits' => (string) ((float) $package->bonus_credits),
            ],
        ];
    }

    /**
     * Verify Whop HMAC-SHA256 webhook signature (supports Standard Webhooks v1,<base64> and hex HMAC).
     */
    public function verifyWebhookSignature(string $rawPayload, ?string $signatureHeader, ?string $webhookId = null, ?string $webhookTimestamp = null): bool
    {
        if (empty($signatureHeader) || $this->webhookSecret === '') {
            return false;
        }

        if (app()->environment('production') && $this->webhookSecret === 'whop_test_secret_2026') {
            return false;
        }

        // 1. Standard Webhooks v1,<base64> signature when webhook-id and webhook-timestamp are present
        if ($webhookId && $webhookTimestamp && str_contains($signatureHeader, 'v1,')) {
            $signedContent = "{$webhookId}.{$webhookTimestamp}.{$rawPayload}";
            $candidateKeys = [$this->webhookSecret];
            if (str_starts_with($this->webhookSecret, 'whsec_')) {
                $decoded = base64_decode(substr($this->webhookSecret, 6), true);
                if ($decoded !== false) {
                    $candidateKeys[] = $decoded;
                }
            }
            if (str_starts_with($this->webhookSecret, 'ws_')) {
                $candidateKeys[] = substr($this->webhookSecret, 3);
            }

            $providedSignatures = preg_split('/\s+/', trim($signatureHeader)) ?: [trim($signatureHeader)];
            foreach ($candidateKeys as $secretKey) {
                $expectedBase64 = 'v1,' . base64_encode(hash_hmac('sha256', $signedContent, $secretKey, true));
                foreach ($providedSignatures as $providedSig) {
                    if ($providedSig !== '' && hash_equals($expectedBase64, $providedSig)) {
                        return true;
                    }
                }
            }
        }

        // 2. Direct hex HMAC-SHA256 signature
        $cleanHeader = preg_replace('/^sha256=/', '', trim($signatureHeader));
        $expectedHex = hash_hmac('sha256', $rawPayload, $this->webhookSecret);

        return hash_equals($expectedHex, (string) $cleanHeader);
    }

    public function generateTestSignature(string $rawPayload): string
    {
        return hash_hmac('sha256', $rawPayload, $this->webhookSecret);
    }
}
