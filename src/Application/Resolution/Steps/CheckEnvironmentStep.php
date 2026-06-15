<?php

declare(strict_types=1);

namespace ABTests\Application\Resolution\Steps;

use ABTests\Enums\Environment;
use ABTests\Application\Resolution\Contracts\ResolutionStep;
use ABTests\Application\Resolution\ResolutionPayload;

/**
 * Gate 2b — environment filter. When an experiment's operational state carries a
 * non-null allowedEnvironments list, units are only eligible when the current
 * application environment appears in that list. A null list means all
 * environments are allowed (backwards-compatible default).
 *
 * Placement in the pipeline: after CheckExperimentActiveStep (so killed /
 * paused experiments are rejected first) and before CheckSegmentStep.
 */
final readonly class CheckEnvironmentStep implements ResolutionStep
{
    public function handle(ResolutionPayload $payload): bool
    {
        $allowed = $payload->state->allowedEnvironments;

        // null = unrestricted — pass every environment through.
        if ($allowed === null) {
            return true;
        }

        // Empty list = disabled in every environment.
        if ($allowed === []) {
            return false;
        }

        $current = Environment::tryFrom((string) app()->environment());

        if ($current === null) {
            // Unknown environment string — do not assign.
            return false;
        }

        return in_array($current->value, $allowed, true);
    }
}
