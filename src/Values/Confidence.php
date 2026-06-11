<?php

declare(strict_types=1);

namespace ABTests\Values;

use InvalidArgumentException;

/**
 * A statistical confidence level (e.g. 0.95) and its derived significance
 * threshold. The threshold is a virtual property computed on read.
 */
final class Confidence
{
    public function __construct(public readonly float $level)
    {
        if ($level <= 0.0 || $level >= 1.0) {
            throw new InvalidArgumentException(
                'Confidence level must be between 0 and 1 exclusive.'
            );
        }
    }

    /** The significance threshold (alpha), e.g. 0.05 for a 0.95 level. */
    public float $significanceThreshold {
        get => 1.0 - $this->level;
    }
}
