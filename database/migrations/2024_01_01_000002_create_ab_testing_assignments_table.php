<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sticky, deterministic bucketing. One row per (experiment_key, unit_type,
 * unit_key) triple. The unique constraint enforces idempotency: the first
 * write wins and subsequent inserts are silently ignored.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ab_testing_assignments', static function (Blueprint $table): void {
            $table->string('experiment_key');
            $table->string('unit_type');
            $table->string('unit_key');
            $table->string('variant_key');
            $table->string('layer')->nullable();
            $table->timestamp('assigned_at');

            $table->primary(['experiment_key', 'unit_type', 'unit_key']);

            // Layer exclusion index: used by findAssignmentByLayer() to enforce
            // that a unit enters at most one running experiment per layer.
            $table->index(['layer', 'unit_type', 'unit_key'], 'ab_assignments_layer_unit_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ab_testing_assignments');
    }
};
