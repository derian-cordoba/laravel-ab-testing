<?php

declare(strict_types=1);

namespace ABTests\Application\Resolution\Contracts;

use ABTests\Application\Resolution\ResolutionPayload;

/**
 * One stage in the resolution pipeline. Returning true continues to the next
 * step; returning false short-circuits the pipeline and signals that the unit
 * should not be assigned to this experiment.
 */
interface ResolutionStep
{
    public function handle(ResolutionPayload $payload): bool;
}
