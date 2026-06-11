<?php

declare(strict_types=1);

namespace ABTests\Resolution\Steps;

use ABTests\Resolution\Contracts\ResolutionStep;
use ABTests\Resolution\ResolutionPayload;

/**
 * Gate 2 — audience targeting. Units that do not match the experiment's
 * audience segment are excluded entirely; they are never assigned, not silently
 * placed in the control arm.
 */
final readonly class CheckSegmentStep implements ResolutionStep
{
    public function handle(ResolutionPayload $payload): bool
    {
        return $payload->definition->audience->matches($payload->unit);
    }
}
