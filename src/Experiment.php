<?php

declare(strict_types=1);

namespace ABTests;

use ABTests\Values\Segment;

/**
 * Base class for experiment definitions. Structural configuration is declared
 * with #[AsExperiment] and the metric/analysis attributes; this class only
 * exposes behavioral hooks consumers may override.
 */
abstract class Experiment
{
    /**
     * Scope the experiment to an eligible audience. Units outside the segment
     * are never assigned (they are excluded, not placed in control).
     */
    public function audience(): Segment
    {
        return Segment::any();
    }
}
