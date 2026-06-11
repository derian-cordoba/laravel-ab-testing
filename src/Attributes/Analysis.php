<?php

declare(strict_types=1);

namespace ABTests\Attributes;

use Attribute;
use ABTests\Enums\StatisticalEngine;

/**
 * Configures how an experiment's results are computed and read.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Analysis
{
    /**
     * @param StatisticalEngine $engine          Which engine(s) to run.
     * @param float             $confidenceLevel Target confidence, e.g. 0.95.
     * @param bool              $sequential      Use always-valid inference so the live
     *                                          dashboard can be read at any time without
     *                                          inflating false positives.
     */
    public function __construct(
        public StatisticalEngine $engine = StatisticalEngine::both,
        public float $confidenceLevel = 0.95,
        public bool $sequential = true,
    ) {
        //
    }
}
