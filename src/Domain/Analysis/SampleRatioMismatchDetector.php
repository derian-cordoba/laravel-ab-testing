<?php

declare(strict_types=1);

namespace ABTests\Domain\Analysis;

use ABTests\Contracts\Variant;
use ABTests\Domain\Analysis\Support\MathFunctions;
use ABTests\Values\Allocation;
use ABTests\Values\MetricSummary;
use ABTests\Values\SampleRatioMismatchResult;

/**
 * Detects a sample-ratio mismatch (SRM) using a chi-square goodness-of-fit
 * test. Compares the observed unit counts across variants against the expected
 * counts implied by the configured allocation weights.
 *
 * An SRM (p < 0.01 by convention) is a data-quality flag: the randomization
 * or event logging pipeline is not working as specified, so the experiment's
 * causal estimates cannot be trusted regardless of what the metric engines say.
 *
 * The Wilson–Hilferty chi-square p-value approximation used here is accurate to
 * 4–5 significant figures for df ≥ 1, which is sufficient for this diagnostic.
 */
final readonly class SampleRatioMismatchDetector
{
    /** SRM significance threshold; lower than the 0.05 used for metrics to reduce false positives. */
    private const float SRM_ALPHA = 0.01;

    /**
     * @param list<MetricSummary> $summaries One entry per variant, in any order.
     */
    public function detect(array $summaries, Allocation $allocation): SampleRatioMismatchResult
    {
        if ($summaries === []) {
            return new SampleRatioMismatchResult(
                detected: false,
                chiSquare: 0.0,
                pValue: 1.0,
            );
        }

        $totalObserved = array_sum(array_map(
            static fn (MetricSummary $s): int => $s->countOfUnits,
            $summaries,
        ));

        if ($totalObserved === 0) {
            return new SampleRatioMismatchResult(
                detected: false,
                chiSquare: 0.0,
                pValue: 1.0,
            );
        }

        $totalWeight = array_sum(array_map(
            static fn (Variant $v): int => $v->weight(),
            $allocation->variants,
        ));

        // Build a lookup of weight by variant key from the allocation, so SRM detection
        // uses the configured allocation weights rather than MetricSummary variant weights
        // (which may be zero when summaries are built from rollup data without weight info).
        $allocationWeights = [];
        foreach ($allocation->variants as $allocationVariant) {
            $allocationWeights[$allocationVariant->key()] = $allocationVariant->weight();
        }

        $chiSquare = 0.0;

        foreach ($summaries as $summary) {
            $weight = $allocationWeights[$summary->variant->key()] ?? $summary->variant->weight();
            $expectedProportion = $totalWeight > 0 ? $weight / $totalWeight : 0.0;
            $expected = $totalObserved * $expectedProportion;

            if ($expected <= 0.0) {
                continue;
            }

            $diff = $summary->countOfUnits - $expected;
            $chiSquare += ($diff * $diff) / $expected;
        }

        $df = max(1, count($summaries) - 1);
        $pValue = MathFunctions::chiSquareSurvivalFunction($chiSquare, $df);

        return new SampleRatioMismatchResult(
            detected: $pValue < self::SRM_ALPHA,
            chiSquare: $chiSquare,
            pValue: $pValue,
        );
    }
}
