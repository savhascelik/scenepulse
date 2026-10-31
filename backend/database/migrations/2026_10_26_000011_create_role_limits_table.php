<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_limits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('role_id')->index();
            $table->string('resource_type', 64)->index();
            $table->string('limit_key', 64);
            $table->integer('limit_value')->default(0);
            $table->string('description')->nullable();
            $table->timestamps();

            $table->unique(['role_id', 'resource_type', 'limit_key'], 'role_limits_unique_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_limits');
    }
};
