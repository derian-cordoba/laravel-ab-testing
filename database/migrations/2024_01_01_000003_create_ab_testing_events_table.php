<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only source of truth for all experiment events. Every exposure,
 * conversion, and metric event lands here first; rollups are derived from it.
 *
 * Uses experiment_key (string) rather than a FK for v1 simplicity. The
 * idempotency_key unique constraint ensures duplicate fires are discarded at
 * the database level without raising exceptions.
 */
return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('ab_testing_events', function (Blueprint $table): void {
            $table->id();
            $table->string('experiment_key');
            $table->string('unit_type');
            $table->string('unit_key');
            $table->string('variant_key');
            $table->string('type'); // EventType enum value
            $table->string('metric_key')->nullable();
            $table->double('value')->nullable();
            $table->json('properties')->nullable();
            $table->string('idempotency_key')->unique();
            $table->timestamp('occurred_at');

            $table->index(['experiment_key', 'occurred_at'], 'ab_events_exp_time_index');
            $table->index(
                ['experiment_key', 'variant_key', 'metric_key', 'occurred_at'],
                'ab_events_rollup_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ab_testing_events');
    }
};
