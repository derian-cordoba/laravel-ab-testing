<?php

declare(strict_types=1);

namespace ABTests\Values;

/**
 * The outcome of a sample-ratio mismatch (SRM) chi-square test. A detected
 * SRM means the observed variant proportions deviate significantly from the
 * configured weights, which invalidates the experiment's causal interpretation.
 */
final readonly class SampleRatioMismatchResult
{
    public function __construct(
        public bool $detected,
        public float $chiSquare,
        public float $pValue,
    ) {
        //
    }
}
