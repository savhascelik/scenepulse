<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_models', function (Blueprint $table) {
            $table->id();
            $table->string('provider_slug', 48)->index(); // nebius, cloudflare_aig, openai, gemini_compat, openrouter, ollama
            $table->string('display_name', 140);
            $table->string('base_url', 320);
            $table->string('api_key_env', 64)->default('NEBIUS_API_KEY');
            $table->string('model_id', 160);
            $table->string('task_role', 48)->index(); // architect, fast_diagnose, datasheet_synth, vision
            $table->json('default_extra_payload')->nullable();
            $table->boolean('is_default')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('ai_usage_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('ai_model_id')->nullable()->constrained('ai_models')->nullOnDelete();
            $table->string('task_role', 48)->index();
            $table->string('provider_slug', 48);
            $table->string('model_id', 160);
            $table->unsignedInteger('prompt_tokens')->default(0);
            $table->unsignedInteger('completion_tokens')->default(0);
            $table->unsignedInteger('latency_ms')->default(0);
            $table->boolean('used_byok')->default(false);
            $table->boolean('cache_hit')->default(false);
            $table->string('status', 32)->default('success');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usage_logs');
        Schema::dropIfExists('ai_models');
    }
};
