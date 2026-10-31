<?php

namespace App\Services;

use App\Models\AiCreditBalance;
use App\Models\AiCreditOrder;
use Illuminate\Support\Facades\DB;

class OrderFulfillmentService
{
    /**
     * Atomically and idempotently fulfill an AI Credit Order and credit the user's balance.
     *
     * @return array{already_completed: bool, order: AiCreditOrder, balance: AiCreditBalance, credits_added: float}
     */
    public function fulfillOrder(AiCreditOrder $order, ?string $whopPaymentId = null, array $webhookMetadata = []): array
    {
        return DB::transaction(function () use ($order, $whopPaymentId, $webhookMetadata) {
            /** @var AiCreditOrder $lockedOrder */
            $lockedOrder = AiCreditOrder::where('id', $order->id)->lockForUpdate()->firstOrFail();

            if ($lockedOrder->status === 'completed') {
                $balance = AiCreditBalance::getOrCreateForUser($lockedOrder->user_id);

                return [
                    'already_completed' => true,
                    'order' => $lockedOrder,
                    'balance' => $balance,
                    'credits_added' => 0.0,
                ];
            }

            if ($whopPaymentId) {
                $duplicatePayment = AiCreditOrder::query()
                    ->where('whop_payment_id', $whopPaymentId)
                    ->where('status', 'completed')
                    ->where('id', '!=', $lockedOrder->id)
                    ->exists();

                if ($duplicatePayment) {
                    $balance = AiCreditBalance::getOrCreateForUser($lockedOrder->user_id);

                    return [
                        'already_completed' => true,
                        'order' => $lockedOrder,
                        'balance' => $balance,
                        'credits_added' => 0.0,
                    ];
                }
            }

            $balance = AiCreditBalance::getOrCreateForUser($lockedOrder->user_id);

            $baseCredits = (float) $lockedOrder->credits_amount;
            $bonusCredits = (float) $lockedOrder->bonus_credits;
            $totalAdded = round($baseCredits + $bonusCredits, 2);

            if ($baseCredits > 0) {
                $balance->addPurchasedCredits(
                    $baseCredits,
                    "AI Kredi Paketi Satın Alımı (#{$lockedOrder->order_number})",
                    'ai_credit_order',
                    $lockedOrder->id,
                    [
                        'order_number' => $lockedOrder->order_number,
                        'whop_payment_id' => $whopPaymentId,
                        'price_paid_usd' => (float) $lockedOrder->price_paid_usd,
                    ]
                );
            }

            if ($bonusCredits > 0) {
                $balance->addPromotionalCredits(
                    $bonusCredits,
                    'purchase_bonus',
                    "Bonus AI Credits for Package (#{$lockedOrder->order_number})",
                    'ai_credit_order',
                    $lockedOrder->id,
                    [
                        'order_number' => $lockedOrder->order_number,
                        'whop_payment_id' => $whopPaymentId,
                    ]
                );
            }

            $lockedOrder->update([
                'status' => 'completed',
                'whop_payment_id' => $whopPaymentId ?: ($lockedOrder->whop_payment_id ?: 'whop_pay_' . strtolower($lockedOrder->order_number)),
                'paid_at' => now(),
                'metadata' => array_merge($lockedOrder->metadata ?? [], $webhookMetadata),
            ]);

            return [
                'already_completed' => false,
                'order' => $lockedOrder->fresh(['package']),
                'balance' => $balance->fresh(),
                'credits_added' => $totalAdded,
            ];
        });
    }
}
