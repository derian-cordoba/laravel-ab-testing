<?php

declare(strict_types=1);

namespace ABTests\Attributes;

use ABTests\Metric;
use Attribute;

/**
 * Designates the single metric that drives the ship / do-not-ship decision.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class PrimaryMetric
{
    /**
     * @param  class-string<Metric>  $metric
     */
    public function __construct(public string $metric)
    {
        //
    }
}
