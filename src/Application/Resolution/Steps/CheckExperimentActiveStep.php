<?php

declare(strict_types=1);

namespace ABTests\Application\Resolution\Steps;

use ABTests\Application\Resolution\Contracts\ResolutionStep;
use ABTests\Application\Resolution\ResolutionPayload;

/**
 * Gate 1 — operational status. An experiment that is not in the running state,
 * or whose kill switch has been pulled, must never assign any unit. This is the
 * first check so nothing else runs for paused/completed experiments.
 */
final readonly class CheckExperimentActiveStep implements ResolutionStep
{
    public function handle(ResolutionPayload $payload): bool
    {
        return $payload->state->isActive();
    }
}
