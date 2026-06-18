<?php

declare(strict_types=1);

namespace ABTests\Domain\Analysis;

use ABTests\Contracts\AnalysisEngine;
use ABTests\Domain\Analysis\Support\MathFunctions;
use ABTests\Enums\StatisticalEngine;
use ABTests\Values\AnalysisConfiguration;
use ABTests\Values\AnalysisResult;
use ABTests\Values\MetricSummary;

/**
 * Two-sample z-test engine. Operates in two modes depending on
 * AnalysisConfiguration::$sequential:
 *
 *  - Non-sequential: standard Wald z-test with fixed-horizon CI.
 *    Valid only at the pre-specified sample size; peeking inflates Type I error.
 *
 *  - Sequential: mixture SPRT (mSPRT) e-value with Gaussian mixing prior
 *    N(0, τ²) where τ = 0.1. The e-value yields an always-valid p-value
 *    (p = 1/E), and the CI uses the Darling-Robbins stitching half-width so
 *    it remains valid at any stopping time.
 *
 * Both modes derive the effect from pre-aggregated MetricSummary sufficient
 * statistics (mean + variance) and therefore work for binary, count, and
 * continuous metrics without modification.
 */
final readonly class FrequentistAnalysisEngine implements AnalysisEngine
{
    /** Gaussian mixing prior standard deviation for the mSPRT. */
    private const float MIXING_TAU = 0.1;

    public function compare(
        MetricSummary $control,
        MetricSummary $treatment,
        AnalysisConfiguration $configuration,
    ): AnalysisResult {
        $delta = $treatment->mean() - $control->mean();
        $alpha = $configuration->confidence->significanceThreshold();

        // For ratio metrics (MetricType::Ratio), use the delta-method variance so
        // the ratio-of-means is compared with the correct sampling variance rather
        // than the naive mean-of-ratios approximation. For all other metric types,
        // fall back to the standard sample variance.
        $controlVariance = $control->isRatioMetric() ? $control->deltaMethodVariance() : $control->variance();
        $treatmentVariance = $treatment->isRatioMetric() ? $treatment->deltaMethodVariance() : $treatment->variance();

        // Variance of (μ_T - μ_C); guard against zero-count arms.
        $varControl = $control->countOfUnits > 0
            ? max($controlVariance, 0.0) / $control->countOfUnits
            : 0.0;

        $varTreatment = $treatment->countOfUnits > 0
            ? max($treatmentVariance, 0.0) / $treatment->countOfUnits
            : 0.0;

        $varDelta = $varControl + $varTreatment;
        $se = sqrt($varDelta);

        $controlMean = $control->mean();
        $relativeLift = $controlMean !== 0.0 ? $delta / abs($controlMean) : 0.0;

        if ($se <= 0.0) {
            // No variance — cannot determine significance.
            return new AnalysisResult(
                engine: StatisticalEngine::frequentist,
                relativeLift: $relativeLift,
                isSignificant: false,
                interval: [$delta, $delta],
                pValue: 1.0,
            );
        }

        if ($configuration->sequential) {
            return $this->sequentialResult($delta, $se, $varDelta, $alpha, $relativeLift);
        }

        return $this->standardResult($delta, $se, $alpha, $relativeLift);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function standardResult(
        float $delta,
        float $se,
        float $alpha,
        float $relativeLift,
    ): AnalysisResult {
        $z = $delta / $se;
        $pValue = 2.0 * (1.0 - MathFunctions::normalCdf(abs($z)));
        $zCritical = MathFunctions::normalQuantile(1.0 - $alpha / 2.0);

        return new AnalysisResult(
            engine: StatisticalEngine::frequentist,
            relativeLift: $relativeLift,
            isSignificant: $pValue < $alpha,
            interval: [$delta - $zCritical * $se, $delta + $zCritical * $se],
            pValue: $pValue,
        );
    }

    /**
     * Sequential result using the mSPRT e-value.
     *
     * E-value with Gaussian mixing prior N(0, τ²):
     *   E = sqrt(σ²_Δ / (σ²_Δ + τ²)) · exp(τ² · δ² / (2 · σ²_Δ · (σ²_Δ + τ²)))
     *
     * Always-valid p-value: p = min(1, 1/E)
     *
     * Always-valid CI half-width (stitching bound):
     *   hw = SE · sqrt(2 · ln(2/α) + ln(1 + n_eff · σ²_Δ / τ²))
     */
    private function sequentialResult(
        float $delta,
        float $se,
        float $varDelta,
        float $alpha,
        float $relativeLift,
    ): AnalysisResult {
        $tau = self::MIXING_TAU;
        $tau2 = $tau * $tau;

        $eValue = sqrt($varDelta / ($varDelta + $tau2))
            * exp($tau2 * $delta * $delta / (2.0 * $varDelta * ($varDelta + $tau2)));

        $pValue = min(1.0, 1.0 / max($eValue, 1e-300));

        // Stitching half-width (Darling–Robbins style).
        $logFactor = 2.0 * log(2.0 / $alpha) + log(1.0 + max($varDelta / $tau2, 1.0));
        $halfWidth = $se * sqrt(max($logFactor, 0.0));

        return new AnalysisResult(
            engine: StatisticalEngine::frequentist,
            relativeLift: $relativeLift,
            isSignificant: $pValue < $alpha,
            interval: [$delta - $halfWidth, $delta + $halfWidth],
            pValue: $pValue,
        );
    }
}
