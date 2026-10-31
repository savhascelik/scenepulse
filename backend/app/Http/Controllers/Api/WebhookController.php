<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiCreditOrder;
use App\Models\AiCreditPackage;
use App\Models\User;
use App\Models\WebhookLog;
use App\Services\OrderFulfillmentService;
use App\Services\WhopService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function __construct(
        private readonly WhopService $whopService,
        private readonly OrderFulfillmentService $fulfillmentService
    ) {}

    /**
     * Handle incoming Whop payment webhooks (`payment.succeeded`, `payment_succeeded`).
     */
    public function handleWhop(Request $request): JsonResponse
    {
        $rawPayload = (string) $request->getContent();
        $signature = $request->header('X-Whop-Signature')
            ?? $request->header('webhook-signature')
            ?? $request->header('x-whop-signature');
        $webhookId = $request->header('webhook-id');
        $webhookTimestamp = $request->header('webhook-timestamp');

        $decoded = json_decode($rawPayload, true);
        $payload = is_array($decoded) ? $decoded : $request->all();

        $eventType = (string) ($payload['action'] ?? $payload['event'] ?? $payload['type'] ?? 'unknown');
        $data = $payload['data'] ?? $payload;
        $externalId = (string) ($data['id'] ?? $payload['id'] ?? '');

        $log = WebhookLog::create([
            'provider' => 'whop',
            'event_type' => $eventType,
            'external_id' => $externalId ?: null,
            'payload' => $payload,
            'status' => 'received',
        ]);

        if (! $this->whopService->verifyWebhookSignature($rawPayload, $signature, $webhookId, $webhookTimestamp)) {
            $log->update([
                'status' => 'failed',
                'error_message' => 'Invalid Whop HMAC-SHA256 signature.',
                'processed_at' => now(),
            ]);

            return response()->json([
                'error' => 'invalid_signature',
                'message' => 'Whop webhook signature verification failed.',
            ], 401);
        }

        // Only process payment completion events
        $normalizedEvent = strtolower(str_replace('.', '_', $eventType));
        if (! in_array($normalizedEvent, ['payment_succeeded', 'invoice_paid', 'membership_went_valid'], true)) {
            $log->update([
                'status' => 'ignored',
                'processed_at' => now(),
            ]);

            return response()->json(['status' => 'ignored', 'event' => $eventType]);
        }

        $metadata = $data['metadata'] ?? $data['checkout']['metadata'] ?? [];
        $orderNumber = $metadata['order_number'] ?? null;
        $orderId = $metadata['order_id'] ?? null;

        /** @var AiCreditOrder|null $order */
        $order = null;
        if ($orderNumber) {
            $order = AiCreditOrder::where('order_number', $orderNumber)->first();
        }
        if (! $order && $orderId) {
            $order = AiCreditOrder::find($orderId);
        }

        // Fallback: if Whop checkout happened directly on Whop without a pre-created order, match by user & package
        if (! $order) {
            $userId = $metadata['user_id'] ?? null;
            $userEmail = $data['user']['email'] ?? $data['email'] ?? ($metadata['user_email'] ?? null);
            $user = $userId ? User::find($userId) : ($userEmail ? User::where('email', strtolower($userEmail))->first() : null);

            $packageId = $metadata['package_id'] ?? null;
            $planId = $data['plan']['id'] ?? $data['plan_id'] ?? null;
            $productId = $data['product']['id'] ?? $data['product_id'] ?? null;
            $package = $packageId
                ? AiCreditPackage::find($packageId)
                : ($planId
                    ? AiCreditPackage::where('whop_plan_id', $planId)->orWhere('whop_product_id', $planId)->first()
                    : ($productId
                        ? AiCreditPackage::where('whop_product_id', $productId)->orWhere('whop_plan_id', $productId)->first()
                        : AiCreditPackage::where('is_active', true)->orderBy('sort_order')->first()));

            if ($user && $package) {
                $order = AiCreditOrder::create([
                    'order_number' => AiCreditOrder::generateOrderNumber(),
                    'user_id' => $user->id,
                    'package_id' => $package->id,
                    'credits_amount' => $package->credits,
                    'bonus_credits' => $package->bonus_credits,
                    'price_paid_usd' => $package->price_usd,
                    'currency' => 'USD',
                    'status' => 'pending',
                    'whop_payment_id' => $externalId ?: null,
                    'metadata' => $metadata,
                ]);
            }
        }

        if (! $order) {
            $log->update([
                'status' => 'failed',
                'error_message' => 'Could not resolve AiCreditOrder or User for Whop webhook.',
                'processed_at' => now(),
            ]);

            return response()->json(['error' => 'order_not_found'], 404);
        }

        $result = $this->fulfillmentService->fulfillOrder($order, $externalId ?: null, [
            'whop_event' => $eventType,
            'webhook_log_id' => $log->id,
        ]);

        $log->update([
            'status' => 'processed',
            'processed_at' => now(),
        ]);

        return response()->json([
            'status' => 'processed',
            'already_completed' => $result['already_completed'],
            'order_number' => $result['order']->order_number,
            'credits_added' => $result['credits_added'],
            'available_credits' => $result['balance']->available_credits,
        ]);
    }
}
