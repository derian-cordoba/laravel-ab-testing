<?php

declare(strict_types=1);

namespace ABTests\Contracts;

/**
 * Port for reading pre-experiment covariate data used by CUPED variance reduction.
 * Keeps the domain analysis layer (AnalysisService, CupedVarianceReduction) free
 * from any knowledge of the underlying storage schema.
 */
interface CovariateRepository
{
    /**
     * Return true when any covariate rows exist for the given experiment and metric.
     * Used as a cheap guard before running the full CUPED computation.
     */
    public function hasAny(string $experimentKey, string $metricKey): bool;

    /**
     * Load per-variant covariate statistics needed for the CUPED theta calculation.
     *
     * @return array<string, array{mean: float, variance: float, cov_yx: float, n: int}>
     *         Keyed by variant_key.
     */
    public function loadStatsPerVariant(string $experimentKey, string $metricKey): array;

    /**
     * Compute the global covariate mean X̄ across all units (all variants combined).
     */
    public function globalMean(string $experimentKey, string $metricKey): float;
}
