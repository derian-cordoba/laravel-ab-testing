<?php

declare(strict_types=1);

namespace ABTests;

/**
 * Base class for metric definitions. Configuration (event, type, aggregate,
 * attribution window) is declared with #[AsMetric]. Override valueOf() only
 * when deriving a continuous value needs custom logic beyond reading a single
 * event property.
 */
abstract class Metric
{
    /**
     * Derive this metric's numeric contribution from a recorded event's
     * properties. Defaults to 1.0 so a presence-based (binary) metric counts
     * each qualifying event once.
     *
     * @param array<string, mixed> $properties
     */
    public function valueOf(array $properties): float
    {
        return 1.0;
    }
}
