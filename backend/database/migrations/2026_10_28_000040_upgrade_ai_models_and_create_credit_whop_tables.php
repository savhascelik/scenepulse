<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_models', function (Blueprint $table) {
            if (! Schema::hasColumn('ai_models', 'model_family')) {
                $table->string('model_family', 64)->nullable()->after('model_id');
                $table->decimal('input_cost_per_1m', 10, 6)->default(0.150000)->after('task_role');
                $table->decimal('output_cost_per_1m', 10, 6)->default(0.600000)->after('input_cost_per_1m');
                $table->decimal('token_multiplier', 5, 2)->default(2.00)->after('output_cost_per_1m');
                $table->decimal('input_multiplier', 5, 2)->nullable()->after('token_multiplier');
                $table->decimal('output_multiplier', 5, 2)->nullable()->after('input_multiplier');
                $table->json('pricing_tiers')->nullable()->after('output_multiplier');
                $table->boolean('supports_vision')->default(false)->after('pricing_tiers');
                $table->boolean('supports_function_calling')->default(true)->after('supports_vision');
                $table->boolean('supports_code_mode')->default(true)->after('supports_function_calling');
                $table->boolean('supports_thinking')->default(false)->after('supports_code_mode');
                $table->unsignedInteger('max_input_tokens')->default(128000)->after('supports_thinking');
                $table->unsignedInteger('max_output_tokens')->default(8192)->after('max_input_tokens');
                $table->decimal('default_temperature', 3, 2)->default(0.25)->after('max_output_tokens');
                $table->unsignedInteger('context_budget_max_tokens')->nullable()->after('default_temperature');
                $table->integer('sort_order')->default(0)->after('is_active');
                $table->text('description')->nullable()->after('sort_order');
            }
        });

        if (! Schema::hasTable('ai_credit_balances')) {
            Schema::create('ai_credit_balances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
                $table->decimal('promotional_credits', 14, 4)->default(0);
                $table->decimal('purchased_credits', 14, 4)->default(0);
                $table->decimal('total_earned_promotional', 14, 4)->default(0);
                $table->decimal('total_purchased', 14, 4)->default(0);
                $table->decimal('total_spent', 14, 4)->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ai_credit_transactions')) {
            Schema::create('ai_credit_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('type', 40)->index(); // signup_grant, purchase, spend, admin_adjust, refund
                $table->string('bucket', 32)->default('mixed'); // promotional, purchased, mixed
                $table->decimal('amount', 14, 4);
                $table->decimal('balance_after', 14, 4);
                $table->string('description', 255)->nullable();
                $table->string('reference_type', 80)->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ai_credit_usages')) {
            Schema::create('ai_credit_usages', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('ai_model_id')->nullable()->constrained('ai_models')->nullOnDelete();
                $table->string('action_type', 64)->index();
                $table->string('provider_slug', 48);
                $table->string('model_id', 160);
                $table->unsignedInteger('input_tokens')->default(0);
                $table->unsignedInteger('output_tokens')->default(0);
                $table->unsignedInteger('total_tokens')->default(0);
                $table->decimal('raw_cost_usd', 12, 6)->default(0);
                $table->decimal('credits_charged', 12, 4)->default(0);
                $table->unsignedInteger('latency_ms')->default(0);
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ai_credit_packages')) {
            Schema::create('ai_credit_packages', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120);
                $table->string('slug', 80)->unique();
                $table->decimal('credits', 12, 2);
                $table->decimal('bonus_credits', 12, 2)->default(0);
                $table->decimal('price_usd', 10, 2);
                $table->string('whop_product_id', 120)->nullable();
                $table->string('whop_plan_id', 120)->nullable();
                $table->string('whop_checkout_url', 500)->nullable();
                $table->boolean('is_popular')->default(false);
                $table->boolean('is_active')->default(true)->index();
                $table->integer('sort_order')->default(0);
                $table->string('description', 255)->nullable();
                $table->json('features')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('ai_credit_orders')) {
            Schema::create('ai_credit_orders', function (Blueprint $table) {
                $table->id();
                $table->string('order_number', 64)->unique();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('package_id')->constrained('ai_credit_packages')->cascadeOnDelete();
                $table->decimal('credits_amount', 12, 2);
                $table->decimal('bonus_credits', 12, 2)->default(0);
                $table->decimal('price_paid_usd', 10, 2);
                $table->string('currency', 10)->default('USD');
                $table->string('status', 32)->default('pending')->index(); // pending, completed, failed, refunded
                $table->string('whop_payment_id', 140)->nullable()->index();
                $table->string('whop_checkout_id', 140)->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('webhook_logs')) {
            Schema::create('webhook_logs', function (Blueprint $table) {
                $table->id();
                $table->string('provider', 32)->default('whop');
                $table->string('event_type', 80)->index();
                $table->string('external_id', 140)->nullable()->index();
                $table->json('payload');
                $table->string('status', 32)->default('received')->index(); // received, processed, ignored, failed
                $table->text('error_message')->nullable();
                $table->timestamp('processed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
        Schema::dropIfExists('ai_credit_orders');
        Schema::dropIfExists('ai_credit_packages');
        Schema::dropIfExists('ai_credit_usages');
        Schema::dropIfExists('ai_credit_transactions');
        Schema::dropIfExists('ai_credit_balances');
    }
};
