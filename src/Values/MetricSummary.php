<?php

declare(strict_types=1);

namespace ABTests\Values;

use ABTests\Contracts\Variant;

/**
 * Pre-aggregated sufficient statistics for one variant and one metric. These
 * are exactly what both engines need, so the analysis layer never rescans the
 * raw event stream.
 *
 * Ratio metric support (MetricType::Ratio):
 *   When sumOfDenominators / sumOfSquaredDenominators / sumOfNumeratorDenominator
 *   are provided, deltaMethodVariance() replaces the naive variance so the
 *   FrequentistAnalysisEngine can apply the delta method for ratio metrics.
 *   These three fields are null for non-ratio metrics.
 */
final class MetricSummary
{
    public function __construct(
        public readonly Variant $variant,
        public readonly int $countOfUnits,
        public readonly float $sumOfValues,
        public readonly float $sumOfSquaredValues,
        public readonly int $conversions,
        // Ratio-metric sufficient statistics (null for non-ratio metrics).
        public readonly ?float $sumOfDenominators = null,
        public readonly ?float $sumOfSquaredDenominators = null,
        public readonly ?float $sumOfNumeratorDenominator = null,
    ) {
        //
    }

    public function mean(): float
    {
        return $this->countOfUnits > 0
            ? $this->sumOfValues / $this->countOfUnits
            : 0.0;
    }

    public function variance(): float
    {
        if ($this->countOfUnits < 2) {
            return 0.0;
        }

        $mean = $this->mean();

        return ($this->sumOfSquaredValues / $this->countOfUnits) - ($mean * $mean);
    }

    public function conversionRate(): float
    {
        return $this->countOfUnits > 0
            ? $this->conversions / $this->countOfUnits
            : 0.0;
    }

    /**
     * Whether this summary carries ratio sufficient statistics. When true,
     * FrequentistAnalysisEngine uses deltaMethodVariance() instead of variance().
     */
    public function isRatioMetric(): bool
    {
        return $this->sumOfDenominators !== null;
    }

    /**
     * Delta-method variance estimate for ratio metrics Y = N / D.
     *
     *   μN = sumOfValues / n        (mean numerator per unit)
     *   μD = sumOfDenominators / n  (mean denominator per unit)
     *   μY = μN / μD                (ratio estimate)
     *
     *   Var(N) = E[N²] - μN²  =  sumOfSquaredValues/n - μN²
     *   Var(D) = E[D²] - μD²  =  sumOfSquaredDenominators/n - μD²
     *   Cov(N,D) = E[ND] - μNμD  =  sumOfNumeratorDenominator/n - μNμD
     *
     *   Var(Y) ≈ (1/μD²) * [Var(N) + μY² * Var(D) - 2μY * Cov(N,D)]
     *
     * Returns 0.0 when the ratio sufficient statistics are absent or the
     * denominator mean is zero (degenerate case).
     */
    public function deltaMethodVariance(): float
    {
        if (
            $this->countOfUnits < 2
            || $this->sumOfDenominators === null
            || $this->sumOfSquaredDenominators === null
            || $this->sumOfNumeratorDenominator === null
        ) {
            return 0.0;
        }

        $n = (float) $this->countOfUnits;
        $muN = $this->sumOfValues / $n;
        $muD = $this->sumOfDenominators / $n;

        if ($muD === 0.0) {
            return 0.0;
        }

        $muY = $muN / $muD;
        $varN = ($this->sumOfSquaredValues / $n) - ($muN * $muN);
        $varD = ($this->sumOfSquaredDenominators / $n) - ($muD * $muD);
        $covND = ($this->sumOfNumeratorDenominator / $n) - ($muN * $muD);

        $variance = (1.0 / ($muD * $muD)) * (
            max($varN, 0.0)
            + ($muY * $muY) * max($varD, 0.0)
            - 2.0 * $muY * $covND
        );

        return max($variance, 0.0);
    }
}
