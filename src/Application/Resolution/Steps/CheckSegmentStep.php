<?php

declare(strict_types=1);

namespace ABTests\Application\Resolution\Steps;

use ABTests\Application\Resolution\Contracts\ResolutionStep;
use ABTests\Application\Resolution\ResolutionPayload;
use ABTests\Enums\EvaluationReason;

/**
 * Gate 2 — audience targeting. Units that do not match the experiment's
 * audience segment are excluded entirely; they are never assigned, not silently
 * placed in the control arm.
 */
final readonly class CheckSegmentStep implements ResolutionStep
{
    public function handle(ResolutionPayload $payload): bool
    {
        if (! $payload->definition->audience->matches($payload->unit)) {
            $payload->stopReason = EvaluationReason::notInAudience;

            return false;
        }

        return true;
    }
}
