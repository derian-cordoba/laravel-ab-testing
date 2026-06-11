<?php

declare(strict_types=1);

namespace ABTests\Attributes;

use Attribute;
use ABTests\Metric;

/**
 * A supporting metric, observed but not decision-driving. Repeatable.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class SecondaryMetric
{
    /**
     * @param class-string<Metric> $metric
     */
    public function __construct(public string $metric)
    {
        //
    }
}
