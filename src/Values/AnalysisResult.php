<?php

declare(strict_types=1);

namespace ABTests\Values;

use ABTests\Enums\StatisticalEngine;

/**
 * The outcome of comparing one treatment arm to control for one metric.
 * Nullable fields carry the figures specific to whichever engine produced it.
 */
final readonly class AnalysisResult
{
    /**
     * @param array{0: float, 1: float} $interval Lower and upper bound.
     */
    public function __construct(
        public StatisticalEngine $engine,
        public float $relativeLift,
        public bool $isSignificant,
        public array $interval,
        public ?float $pValue = null,                    // frequentist
        public ?float $probabilityToBeatControl = null,  // bayesian
        public ?float $expectedLoss = null,              // bayesian
    ) {
        //
    }
}
