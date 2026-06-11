<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Operational state for feature flags. Schema-only in v1 — the dashboard UI
 * surface for flags is planned for v2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ab_testing_feature_flag_states', static function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->boolean('is_enabled')->default(false);
            $table->unsignedInteger('rollout_percentage')->default(0);
            $table->timestamp('killed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ab_testing_feature_flag_states');
    }
};
