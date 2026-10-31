<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('plans')) {
            Schema::create('plans', function (Blueprint $table) {
                $table->id();
                $table->string('name', 160);
                $table->string('slug', 120)->unique();
                $table->string('description', 500)->nullable();
                $table->foreignId('role_id')->nullable()->constrained('roles')->nullOnDelete();
                $table->string('whop_plan_id', 160)->nullable()->index();
                $table->decimal('price', 10, 2)->default(0.00);
                $table->string('price_suffix', 40)->default('/month');
                $table->string('currency', 3)->default('USD');
                $table->decimal('monthly_points', 14, 4)->default(0.0000);
                $table->decimal('bonus_points', 14, 4)->default(0.0000);
                $table->json('features')->nullable();
                $table->string('cta_text', 80)->default('Get Started');
                $table->boolean('is_highlighted')->default(false);
                $table->boolean('is_active')->default(true);
                $table->integer('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('subscriptions')) {
            Schema::create('subscriptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('plan_id')->constrained('plans')->cascadeOnDelete();
                $table->string('source', 40)->default('direct');
                $table->string('whop_membership_id', 160)->nullable()->index();
                $table->string('status', 40)->default('active')->index();
                $table->timestamp('trial_ends_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('plans');
    }
};
