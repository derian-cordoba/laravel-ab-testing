<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the three additional sufficient statistics required by the delta method
 * for MetricType::Ratio metrics.
 *
 * For a ratio metric Y = N / D (e.g. revenue per session), the delta method
 * estimates Var(Y) using:
 *   Var(Y) ≈ (1/n²) * [ Var(N) + μY² * Var(D) - 2μY * Cov(N,D) ]
 *
 * This requires per-unit sums of the denominator and the cross-product N*D,
 * which cannot be derived from the existing sum_of_values / sum_of_squared_values
 * columns (those cover the numerator only).
 *
 * These columns are nullable so existing rows remain valid; the rollup job
 * populates them only for ratio-typed metrics.
 */
return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('ab_testing_rollups', static function (Blueprint $table): void {
            // Sum of denominator values across units (Σ D_i).
            $table->double('sum_of_denominators')->nullable()->default(null)->after('sum_of_squared_values');

            // Sum of squared denominator values across units (Σ D_i²).
            $table->double('sum_of_squared_denominators')->nullable()->default(null)->after('sum_of_denominators');

            // Sum of the product numerator × denominator across units (Σ N_i * D_i).
            $table->double('sum_of_numerator_denominator')->nullable()->default(null)->after('sum_of_squared_denominators');
        });
    }

    public function down(): void
    {
        Schema::table('ab_testing_rollups', static function (Blueprint $table): void {
            $table->dropColumn([
                'sum_of_denominators',
                'sum_of_squared_denominators',
                'sum_of_numerator_denominator',
            ]);
        });
    }
};
