<?php

declare(strict_types=1);

namespace ABTests\Application\Resolution\Steps;

use ABTests\Application\Resolution\Contracts\ResolutionStep;
use ABTests\Application\Resolution\ResolutionPayload;

/**
 * Gate 3 — traffic holdout. Only units whose deterministic bucket position
 * falls within the experiment's configured traffic percentage participate.
 * Units outside this slice are held out and never assigned.
 *
 * Because the same position is used here and in BucketStep, traffic ramp-ups
 * are stable: a unit at position 0.45 that becomes eligible when traffic moves
 * from 40% to 50% will be assigned to the same variant slice every time.
 */
final readonly class CheckTrafficAllocationStep implements ResolutionStep
{
    public function handle(ResolutionPayload $payload): bool
    {
        return $payload->bucketPosition < ($payload->state->trafficPercentage / 100);
    }
}
