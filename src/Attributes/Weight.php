<?php

declare(strict_types=1);

namespace ABTests\Attributes;

use Attribute;

/**
 * Sets the traffic share of a variant enum case as a whole percentage.
 * Every case must carry one, and the weights across an enum must sum to 100.
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
final readonly class Weight
{
    public function __construct(public int $percentage)
    {
    }
}
