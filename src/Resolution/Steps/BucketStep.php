<?php

declare(strict_types=1);

namespace ABTests\Resolution\Steps;

use ABTests\Resolution\Contracts\ResolutionStep;
use ABTests\Resolution\ResolutionPayload;

/**
 * Step 6 — deterministic variant assignment. Maps the unit's pre-computed
 * bucket position to the variant that owns that slice of the allocation.
 *
 * Skipped when LoadExistingAssignmentStep already populated resolvedVariant,
 * preserving the sticky assignment without re-hashing.
 */
final readonly class BucketStep implements ResolutionStep
{
    public function handle(ResolutionPayload $payload): bool
    {
        if ($payload->hasExistingAssignment) {
            return true;
        }

        $payload->resolvedVariant = $payload->definition->allocation->variantAt(
            $payload->bucketPosition
        );

        return true;
    }
}
