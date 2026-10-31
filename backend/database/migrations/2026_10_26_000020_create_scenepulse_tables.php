<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_items', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->string('creator_name')->default('@flownie_pulse');
            $table->string('creator_handle')->default('@flownie_pulse');
            $table->string('creator_followers')->default('148.5k takipçi');
            $table->string('published_label')->default('31 Ekim 2026');
            $table->string('card_index_label')->default('0001 / 0006');
            $table->json('category_pills')->nullable();
            $table->string('video_url');
            $table->string('poster_url')->nullable();
            $table->unsignedInteger('duration_seconds')->default(35);
            $table->string('duration_badge')->default('0:35');
            $table->string('views_label')->default('121,4k');
            $table->string('likes_label')->default('1.9 bin');
            $table->string('bookmarks_label')->default('3.4k');
            $table->string('shares_label')->default('226');
            $table->json('default_visual_signals')->nullable();
            $table->json('default_audio_signals')->nullable();
            $table->json('qloo_seed_queries')->nullable();
            $table->text('editorial_summary')->nullable();
            $table->unsignedInteger('sort_order')->default(1);
            $table->boolean('is_featured')->default(true);
            $table->timestamps();
        });

        Schema::create('scene_pulse_impulses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('video_item_id')->nullable()->constrained('video_items')->nullOnDelete();
            $table->unsignedInteger('time_bucket')->default(0)->index();
            $table->string('timestamp_formatted')->default('00:00');
            $table->string('frame_hash')->unique();
            $table->string('scene_headline');
            $table->json('visual_signals');
            $table->json('audio_10s_signals');
            $table->json('qloo_resolved_entities')->nullable();
            $table->json('qloo_taste_tags')->nullable();
            $table->json('ad_offers');
            $table->json('metrics')->nullable();
            $table->string('ai_provider')->default('nebius+qloo');
            $table->boolean('qloo_live_verified')->default(false);
            $table->unsignedInteger('latency_ms')->default(420);
            $table->unsignedInteger('hit_count')->default(1);
            $table->timestamps();

            $table->index(['video_item_id', 'time_bucket']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scene_pulse_impulses');
        Schema::dropIfExists('video_items');
    }
};
