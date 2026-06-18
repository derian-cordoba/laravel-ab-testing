<?php

declare(strict_types=1);

namespace ABTests\Application\Resolution\Steps;

use ABTests\Contracts\AssignmentRepository;
use ABTests\Application\Resolution\Contracts\ResolutionStep;
use ABTests\Application\Resolution\ResolutionPayload;
use ABTests\Values\Assignment;
use DateTimeImmutable;

/**
 * Step 7 — sticky persistence. Writes the freshly-computed assignment to the
 * repository so that all subsequent resolutions for this unit on this
 * experiment return the same variant.
 *
 * Skipped when:
 *   • the assignment already existed (LoadExistingAssignmentStep set hasExistingAssignment),
 *   • no variant was resolved (should not happen in a correctly configured pipeline, but guarded defensively), or
 *   • the payload carries dryRun === true (used by isEligible() and other read-only paths).
 */
final readonly class PersistAssignmentStep implements ResolutionStep
{
    public function __construct(private AssignmentRepository $assignmentRepository)
    {
        //
    }

    public function handle(ResolutionPayload $payload): bool
    {
        if ($payload->dryRun || $payload->hasExistingAssignment || $payload->resolvedVariant === null) {
            return true;
        }

        $this->assignmentRepository->storeAssignment(new Assignment(
            experimentKey: $payload->definition->key,
            unitType: $payload->definition->unitType,
            unitKey: $payload->unit->bucketingKey(),
            variantKey: $payload->resolvedVariant->key(),
            layer: $payload->definition->layer,
            assignedAt: new DateTimeImmutable(),
        ));

        return true;
    }
}
