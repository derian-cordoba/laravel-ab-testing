<?php

declare(strict_types=1);

namespace ABTests\Application\Resolution\Steps;

use ABTests\Application\Resolution\Contracts\ResolutionStep;
use ABTests\Application\Resolution\ResolutionPayload;
use ABTests\Enums\EvaluationReason;

/**
 * Gate 1 — operational status. An experiment that is not in the running state,
 * or whose kill switch has been pulled, must never assign any unit. This is the
 * first check so nothing else runs for paused/completed experiments.
 *
 * Sets stopReason on the payload before returning false so the Resolver can
 * distinguish between a kill-switch rejection and a lifecycle-state rejection.
 */
final readonly class CheckExperimentActiveStep implements ResolutionStep
{
    public function handle(ResolutionPayload $payload): bool
    {
        if ($payload->state->isKilled) {
            $payload->stopReason = EvaluationReason::experimentKilled;

            return false;
        }

        if (! $payload->state->status->isLive()) {
            $payload->stopReason = EvaluationReason::experimentNotRunning;

            return false;
        }

        return true;
    }
}
