<?php

declare(strict_types=1);

namespace ABTests\Values;

use ABTests\Enums\Verdict;

/**
 * The complete outcome of a statistical analysis run: a high-level shipping
 * decision plus the raw engine outputs and SRM diagnostic that produced it.
 */
final readonly class VerdictResult
{
    public function __construct(
        public Verdict $verdict,
        public ?AnalysisResult $frequentist,
        public ?AnalysisResult $bayesian,
        public SampleRatioMismatchResult $srm,
    ) {
        //
    }
}
