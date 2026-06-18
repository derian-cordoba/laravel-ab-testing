<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores pre-experiment covariate observations used by CUPED variance reduction.
 *
 * CUPED (Controlled-experiment Using Pre-Experiment Data) adjusts each unit's
 * observed outcome by its pre-experiment value of the same metric, reducing
 * residual variance and yielding the same statistical power with ~30–50% fewer
 * samples on average.
 *
 * A covariate row records the metric value for a unit during a reference period
 * (typically the 7–30 days before the experiment started). The rollup job reads
 * these rows when computing adjusted MetricSummary statistics.
 *
 * One row per (experiment_key, metric_key, unit_type, unit_key).
 */
return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('ab_testing_covariates', static function (Blueprint $table): void {
            $table->id();
            $table->string('experiment_key');
            $table->string('metric_key');
            $table->string('unit_type');
            $table->string('unit_key');
            $table->double('value');
            $table->timestamp('recorded_at');

            $table->unique(
                ['experiment_key', 'metric_key', 'unit_type', 'unit_key'],
                'ab_covariates_unique_index',
            );

            $table->index(
                ['experiment_key', 'metric_key'],
                'ab_covariates_lookup_index',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ab_testing_covariates');
    }
};
