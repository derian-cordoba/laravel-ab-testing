<?php

declare(strict_types=1);

namespace ABTests\Domain\Analysis;

use ABTests\Domain\Analysis\Support\MathFunctions;
use ABTests\Values\PowerAnalysisResult;
use InvalidArgumentException;

/**
 * Sample-size / power calculator for A/B experiments.
 *
 * Supports two formula modes:
 *
 *  - Binary metrics (proportion tests):
 *      n = 2 * (z_α/2 + z_β)² * p̄(1-p̄) / δ²
 *    where p̄ = (p_control + p_treatment) / 2 and δ = |p_treatment - p_control|.
 *
 *  - Continuous metrics (two-sample z-test on means):
 *      n = 2 * (z_α/2 + z_β)² * σ² / δ²
 *    where σ is the pooled standard deviation (provided by the caller) and δ
 *    is the absolute difference in means.
 *
 * When $isRelativeEffect is true, the MDE is interpreted as a fraction of the
 * baseline (e.g. 0.05 = 5% relative lift), and the absolute δ is derived as
 * δ = baseline × mde.
 *
 * The resulting sample size is per variant arm. Multiply by the number of arms
 * to get the total experiment sample size.
 *
 * Assumptions:
 *  - Two-tailed test (α/2 on each side).
 *  - Equal sample sizes across all arms.
 *  - No continuity correction (conservative for small samples).
 */
final readonly class PowerAnalysis
{
    public function __construct(
        private float $confidenceLevel = 0.95,
        private float $power = 0.80,
    ) {
        if ($confidenceLevel <= 0.0 || $confidenceLevel >= 1.0) {
            throw new InvalidArgumentException('confidenceLevel must be in (0, 1).');
        }

        if ($power <= 0.0 || $power >= 1.0) {
            throw new InvalidArgumentException('power must be in (0, 1).');
        }
    }

    /**
     * Compute the required sample size for a binary (proportion / conversion-rate) metric.
     *
     * @param float $baselineRate           Current conversion rate, e.g. 0.12 for 12%.
     * @param float $minimumDetectableEffect The smallest effect worth detecting.
     *                                       Relative when $isRelativeEffect = true (e.g. 0.05 = 5% lift).
     *                                       Absolute otherwise (e.g. 0.01 = 1 pp change).
     * @param bool  $isRelativeEffect        Interpret MDE as a fraction of the baseline.
     * @param int   $numberOfVariants        Total arms including control (minimum 2).
     */
    public function forBinaryMetric(
        float $baselineRate,
        float $minimumDetectableEffect,
        bool $isRelativeEffect = true,
        int $numberOfVariants = 2,
    ): PowerAnalysisResult {
        if ($baselineRate <= 0.0 || $baselineRate >= 1.0) {
            throw new InvalidArgumentException('baselineRate must be in (0, 1).');
        }

        $absoluteDelta = $isRelativeEffect
            ? $baselineRate * $minimumDetectableEffect
            : $minimumDetectableEffect;

        if ($absoluteDelta <= 0.0) {
            throw new InvalidArgumentException('The minimum detectable effect must be positive.');
        }

        $treatmentRate = $baselineRate + $absoluteDelta;

        // Pooled proportion for the variance estimate.
        $pooled = ($baselineRate + $treatmentRate) / 2.0;
        $pooledVariance = $pooled * (1.0 - $pooled);

        $sampleSizePerVariant = $this->computeSampleSize($pooledVariance * 2.0, $absoluteDelta);

        return new PowerAnalysisResult(
            sampleSizePerVariant: $sampleSizePerVariant,
            numberOfVariants: $numberOfVariants,
            baselineRate: $baselineRate,
            minimumDetectableEffect: $minimumDetectableEffect,
            isRelativeEffect: $isRelativeEffect,
            confidenceLevel: $this->confidenceLevel,
            power: $this->power,
            totalSampleSize: $sampleSizePerVariant * $numberOfVariants,
        );
    }

    /**
     * Compute the required sample size for a continuous metric.
     *
     * @param float $baselineMean           Historical / assumed mean of the metric.
     * @param float $standardDeviation      Pooled standard deviation of the metric.
     * @param float $minimumDetectableEffect Smallest mean difference worth detecting.
     *                                       Relative when $isRelativeEffect = true.
     * @param bool  $isRelativeEffect        Interpret MDE as a fraction of the baseline.
     * @param int   $numberOfVariants        Total arms including control (minimum 2).
     */
    public function forContinuousMetric(
        float $baselineMean,
        float $standardDeviation,
        float $minimumDetectableEffect,
        bool $isRelativeEffect = true,
        int $numberOfVariants = 2,
    ): PowerAnalysisResult {
        if ($standardDeviation <= 0.0) {
            throw new InvalidArgumentException('standardDeviation must be positive.');
        }

        $absoluteDelta = $isRelativeEffect
            ? abs($baselineMean) * $minimumDetectableEffect
            : $minimumDetectableEffect;

        if ($absoluteDelta <= 0.0) {
            throw new InvalidArgumentException('The minimum detectable effect must be positive.');
        }

        $pooledVariance = 2.0 * $standardDeviation * $standardDeviation;
        $sampleSizePerVariant = $this->computeSampleSize($pooledVariance, $absoluteDelta);

        return new PowerAnalysisResult(
            sampleSizePerVariant: $sampleSizePerVariant,
            numberOfVariants: $numberOfVariants,
            baselineRate: $baselineMean,
            minimumDetectableEffect: $minimumDetectableEffect,
            isRelativeEffect: $isRelativeEffect,
            confidenceLevel: $this->confidenceLevel,
            power: $this->power,
            totalSampleSize: $sampleSizePerVariant * $numberOfVariants,
        );
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Core sample-size formula: n = (z_α/2 + z_β)² × σ²_pooled / δ²
     *
     * Returns the ceiling (always round up to guarantee the target power).
     */
    private function computeSampleSize(float $pooledVariance, float $absoluteDelta): int
    {
        $alpha = 1.0 - $this->confidenceLevel;
        $zAlpha = MathFunctions::normalQuantile(1.0 - $alpha / 2.0);
        $zBeta  = MathFunctions::normalQuantile($this->power);

        $n = ($zAlpha + $zBeta) ** 2.0 * $pooledVariance / ($absoluteDelta ** 2.0);

        return (int) ceil($n);
    }
}
