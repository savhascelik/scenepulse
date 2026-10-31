<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AiCreditOrder;
use App\Models\AiCreditPackage;
use App\Models\User;
use App\Services\AiCreditService;
use App\Services\WhopService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CreditController extends Controller
{
    public function __construct(
        private readonly AiCreditService $creditService,
        private readonly WhopService $whopService
    ) {}

    public function packages(): JsonResponse
    {
        $packages = AiCreditPackage::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('price_usd')
            ->get()
            ->map(function (AiCreditPackage $pkg) {
                return [
                    'id' => $pkg->id,
                    'name' => $pkg->name,
                    'slug' => $pkg->slug,
                    'credits' => (float) $pkg->credits,
                    'bonus_credits' => (float) $pkg->bonus_credits,
                    'total_credits' => $pkg->total_credits,
                    'price_usd' => (float) $pkg->price_usd,
                    'is_popular' => $pkg->is_popular,
                    'description' => $pkg->description,
                    'features' => $pkg->features ?? [],
                    'whop_product_id' => $pkg->whop_product_id,
                    'whop_plan_id' => $pkg->whop_plan_id ?: ($pkg->whop_product_id ?: "plan_{$pkg->slug}"),
                    'whop_checkout_url' => $pkg->whop_checkout_url,
                ];
            });

        return response()->json([
            'packages' => $packages,
        ]);
    }

    public function balance(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $summary = $this->creditService->getBalanceSummary($user);

        $recentTransactions = $user->creditTransactions()
            ->latest()
            ->limit(15)
            ->get();

        $recentUsages = $user->creditUsages()
            ->latest()
            ->limit(15)
            ->get();

        return response()->json([
            'credits' => $summary,
            'transactions' => $recentTransactions,
            'usages' => $recentUsages,
        ]);
    }

    public function orders(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $orders = $user->creditOrders()
            ->with('package')
            ->latest()
            ->limit(25)
            ->get();

        return response()->json([
            'orders' => $orders,
        ]);
    }

    public function createCheckout(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->can('credits.purchase')) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'Your current role does not have permission to purchase AI credit packages.',
            ], 403);
        }

        $validated = $request->validate([
            'package_id' => ['nullable', 'integer', 'exists:ai_credit_packages,id'],
            'package_slug' => ['nullable', 'string', 'exists:ai_credit_packages,slug'],
        ]);

        /** @var AiCreditPackage $package */
        $package = AiCreditPackage::query()
            ->where('is_active', true)
            ->when(
                ! empty($validated['package_id']),
                fn ($q) => $q->where('id', $validated['package_id']),
                fn ($q) => $q->where('slug', $validated['package_slug'] ?? 'maker-2000')
            )
            ->firstOrFail();

        $order = AiCreditOrder::create([
            'order_number' => AiCreditOrder::generateOrderNumber(),
            'user_id' => $user->id,
            'package_id' => $package->id,
            'credits_amount' => $package->credits,
            'bonus_credits' => $package->bonus_credits,
            'price_paid_usd' => $package->price_usd,
            'currency' => 'USD',
            'status' => 'pending',
            'metadata' => [
                'package_slug' => $package->slug,
                'package_name' => $package->name,
                'initiated_from' => 'studio_credit_modal',
            ],
        ]);

        $checkoutUrl = $this->whopService->createCreditCheckoutUrl($package, $user, $order);
        $checkoutSession = $this->whopService->buildEmbeddedCheckoutConfig($package, $user, $order);

        return response()->json([
            'order' => $order->load('package'),
            'checkout_url' => $checkoutUrl,
            'checkout_session' => $checkoutSession,
        ], 201);
    }

    public function orderStatus(Request $request, string $orderNumber): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $order = $user->creditOrders()
            ->with('package')
            ->where('order_number', $orderNumber)
            ->first();

        if (! $order) {
            return response()->json([
                'ok' => false,
                'error' => 'order_not_found',
                'message' => 'Credit order not found.',
            ], 404);
        }

        $summary = $this->creditService->getBalanceSummary($user->fresh());

        return response()->json([
            'ok' => true,
            'order' => $order,
            'status' => $order->status,
            'completed' => $order->status === 'completed',
            'credits' => $summary,
        ]);
    }
}
