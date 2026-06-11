<?php

declare(strict_types=1);

namespace ABTests\Definitions;

use ABTests\Enums\MetricRole;

/**
 * Associates a metric with the role it plays in one experiment. A guardrail
 * additionally carries the worst tolerated regression.
 */
final readonly class MetricBinding
{
    /**
     * @param string $metric A metric class-string (code-defined) or key (runtime-defined).
     */
    public function __construct(
        public string $metric,
        public MetricRole $role,
        public ?float $maximumRegression = null,
    ) {
    }
}
