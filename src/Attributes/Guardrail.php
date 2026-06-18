<?php

declare(strict_types=1);

namespace ABTests\Attributes;

use ABTests\Metric;
use Attribute;

/**
 * A metric that must not regress. If a treatment arm degrades it beyond the
 * allowed amount, the breach can pause the experiment and alert owners.
 * Repeatable.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class Guardrail
{
    /**
     * @param  class-string<Metric>  $metric
     * @param  float  $maximumRegression  Worst tolerated relative drop, e.g. 0.005 for 0.5%.
     */
    public function __construct(
        public string $metric,
        public float $maximumRegression,
    ) {
        //
    }
}
