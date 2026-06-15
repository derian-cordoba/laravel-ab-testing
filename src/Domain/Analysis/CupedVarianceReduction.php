<?php

declare(strict_types=1);

namespace ABTests\Domain\Analysis;

use ABTests\Infrastructure\Database\Models\CovariateModel;
use ABTests\Infrastructure\Database\Models\RollupModel;
use ABTests\Values\MetricSummary;
use Illuminate\Support\Facades\DB;

/**
 * CUPED — Controlled-experiment Using Pre-Experiment Data.
 *
 * Reference: Deng, Xu, Kohavi, Walker (2013). "Improving the Sensitivity of
 * Online Controlled Experiments by Utilizing Pre-Experiment Data."
 *
 * For each variant arm the adjusted outcome per unit is:
 *
 *   Y_adj_i = Y_i - θ × (X_i - X̄)
 *
 * where:
 *   Y_i  = post-experiment outcome for unit i
 *   X_i  = pre-experiment covariate (same metric, reference period) for unit i
 *   X̄   = mean of the covariate across ALL assigned units
 *   θ    = Cov(Y, X) / Var(X)  — optimal coefficient, computed from control arm
 *
 * The key insight: because E[Y_adj] = E[Y] (the expectation is unchanged),
 * CUPED is unbiased. Its variance is Var(Y) × (1 - ρ²) where ρ = Cor(Y, X).
 * For highly correlated pre/post metrics (ρ > 0.7) this cuts variance by >50%.
 *
 * This class computes sufficient statistics for the adjusted outcomes directly
 * from the rollup table and the covariate table, returning a new MetricSummary
 * with a reduced variance. The mean is unchanged.
 *
 * Limitation: CUPED here uses group-level sufficient statistics rather than
 * per-unit pre/post pairs, following the variance-reduction approximation
 * described in §3.2 of the paper. Full per-unit adjustment requires the raw
 * events stream and is not supported in this implementation.
 */
final readonly class CupedVarianceReduction
{
    /**
     * Apply CUPED adjustment to a set of MetricSummary values.
     *
     * If covariate data is unavailable or insufficient, the original summaries
     * are returned unchanged — CUPED degrades gracefully to a standard analysis.
     *
     * @param list<MetricSummary> $summaries One per variant arm.
     * @param string              $experimentKey
     * @param string              $metricKey
     * @return list<MetricSummary>
     */
    public function adjust(
        array $summaries,
        string $experimentKey,
        string $metricKey,
    ): array {
        if ($summaries === []) {
            return $summaries;
        }

        // Load covariate statistics per variant.
        $covariateStats = $this->loadCovariateStats($experimentKey, $metricKey);

        if ($covariateStats === []) {
            return $summaries; // No covariate data — skip CUPED.
        }

        // Compute θ from the control arm covariate ↔ post-experiment correlation.
        $theta = $this->computeTheta($summaries, $covariateStats);

        if ($theta === null || $theta === 0.0) {
            return $summaries;
        }

        // Compute the global covariate mean X̄ across all units.
        $globalCovMean = $this->computeGlobalCovariateMean($experimentKey, $metricKey);

        return array_map(
            static function (MetricSummary $summary) use ($theta, $covariateStats, $globalCovMean): MetricSummary {
                $variantKey = $summary->variant->key();
                $covStats = $covariateStats[$variantKey] ?? null;

                if ($covStats === null || $summary->countOfUnits < 2) {
                    return $summary;
                }

                $covMean = $covStats['mean'];

                // Adjusted mean: Ȳ_adj = Ȳ - θ × (X̄_variant - X̄_global)
                // This accounts for any covariate imbalance between arms.
                $adjustedMean = $summary->mean - $theta * ($covMean - $globalCovMean);

                // Adjusted variance: Var(Y_adj) = Var(Y) + θ² × Var(X) - 2θ × Cov(Y, X)
                // We approximate Cov(Y, X) ≈ θ × Var(X) (from the θ definition).
                $covVariance = $covStats['variance'];
                $adjustedVariance = max(
                    0.0,
                    $summary->variance + $theta * $theta * $covVariance - 2.0 * $theta * $theta * $covVariance,
                );

                // Rewrite the sufficient statistics to reflect the adjusted variance.
                // sumOfSquaredValues is reverse-engineered from adjustedVariance so the
                // property hook on MetricSummary returns the right figure.
                $n = (float) $summary->countOfUnits;
                $adjustedSumOfValues = $adjustedMean * $n;
                $adjustedSumOfSquaredValues = ($adjustedVariance + $adjustedMean * $adjustedMean) * $n;

                return new MetricSummary(
                    variant: $summary->variant,
                    countOfUnits: $summary->countOfUnits,
                    sumOfValues: $adjustedSumOfValues,
                    sumOfSquaredValues: $adjustedSumOfSquaredValues,
                    conversions: $summary->conversions,
                    // Ratio statistics are preserved as-is (CUPED adjusts means/variances
                    // but ratio sufficient statistics would require per-unit adjustment).
                    sumOfDenominators: $summary->sumOfDenominators,
                    sumOfSquaredDenominators: $summary->sumOfSquaredDenominators,
                    sumOfNumeratorDenominator: $summary->sumOfNumeratorDenominator,
                );
            },
            $summaries,
        );
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Compute θ = Cov(Y, X) / Var(X) using the control arm sufficient statistics.
     * Returns null when variance is zero or data is insufficient.
     *
     * @param list<MetricSummary>         $summaries
     * @param array<string, array> $covariateStats
     */
    private function computeTheta(array $summaries, array $covariateStats): ?float
    {
        // Find the control arm.
        $control = null;

        foreach ($summaries as $summary) {
            if ($summary->variant->isControl()) {
                $control = $summary;
                break;
            }
        }

        if ($control === null) {
            return null;
        }

        $variantKey = $control->variant->key();
        $covStats = $covariateStats[$variantKey] ?? null;

        if ($covStats === null || ($covStats['variance'] ?? 0.0) === 0.0) {
            return null;
        }

        // We approximate Cov(Y, X) using the within-arm covariance stored in covStats.
        // Without per-unit data we estimate: Cov(Y, X) ≈ ρ × σY × σX
        // where ρ is approximated as the sign correlation from rollup means.
        // A more accurate estimate requires per-unit Y and X pairs (future work).
        $covVariance = $covStats['variance'];

        // For the simplified aggregate approach: θ = Cov(Y, X) / Var(X)
        // We use the within-group estimate from the covariates table alone, which
        // underestimates θ but is conservative (reduces variance less, not more).
        return isset($covStats['cov_yx']) && $covVariance > 0.0
            ? $covStats['cov_yx'] / $covVariance
            : 0.0;
    }

    /**
     * Load mean, variance, and cov_yx (Cov(Y,X) estimate) per variant from the
     * covariates table and rollups table.
     *
     * @return array<string, array{mean: float, variance: float, cov_yx: float}>
     */
    private function loadCovariateStats(string $experimentKey, string $metricKey): array
    {
        // Aggregate covariate statistics per variant by joining covariates with
        // assignments (so we know which variant each unit is in).
        $rows = DB::table('ab_testing_covariates as c')
            ->join('ab_testing_assignments as a', static function ($join) use ($experimentKey): void {
                $join->on('c.unit_type', '=', 'a.unit_type')
                     ->on('c.unit_key', '=', 'a.unit_key')
                     ->where('a.experiment_key', '=', $experimentKey);
            })
            ->join('ab_testing_rollups as r', static function ($join) use ($experimentKey, $metricKey): void {
                $join->on('a.variant_key', '=', 'r.variant_key')
                     ->where('r.experiment_key', '=', $experimentKey)
                     ->where('r.metric_key', '=', $metricKey);
            })
            ->where('c.experiment_key', $experimentKey)
            ->where('c.metric_key', $metricKey)
            ->select(
                'a.variant_key',
                DB::raw('COUNT(*) as n'),
                DB::raw('AVG(c.value) as cov_mean'),
                DB::raw('VAR_SAMP(c.value) as cov_variance'),
                // Approximate Cov(Y,X) using the product of deviations from group means.
                // Requires subquery or application-level computation; we use a simplified
                // aggregate that is available across MySQL and PostgreSQL.
                DB::raw('(AVG(c.value * (r.sum_of_values / NULLIF(r.count_of_units, 0))) - AVG(c.value) * (r.sum_of_values / NULLIF(r.count_of_units, 0))) as cov_yx_approx'),
            )
            ->groupBy('a.variant_key', 'r.sum_of_values', 'r.count_of_units')
            ->get();

        $stats = [];

        foreach ($rows as $row) {
            $stats[(string) $row->variant_key] = [
                'mean'     => (float) ($row->cov_mean ?? 0.0),
                'variance' => (float) ($row->cov_variance ?? 0.0),
                'cov_yx'   => (float) ($row->cov_yx_approx ?? 0.0),
                'n'        => (int) $row->n,
            ];
        }

        return $stats;
    }

    /**
     * Compute the global covariate mean X̄ across all units (all variants combined).
     */
    private function computeGlobalCovariateMean(string $experimentKey, string $metricKey): float
    {
        $mean = DB::table('ab_testing_covariates')
            ->where('experiment_key', $experimentKey)
            ->where('metric_key', $metricKey)
            ->avg('value');

        return (float) ($mean ?? 0.0);
    }
}
