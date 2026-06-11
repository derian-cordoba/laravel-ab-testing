<?php

declare(strict_types=1);

namespace ABTests\Resolution\Steps;

use ABTests\Contracts\AssignmentRepository;
use ABTests\Resolution\Contracts\ResolutionStep;
use ABTests\Resolution\ResolutionPayload;

/**
 * Step 4 — sticky assignment rehydration. Looks up a persisted assignment for
 * this unit on this experiment. If one is found and its variant key is still
 * present in the current allocation (i.e. the experiment has not been illegally
 * mutated mid-flight), the payload is populated and downstream steps are told
 * to skip their work via the hasExistingAssignment flag.
 *
 * If the stored variant key is no longer in the allocation (exceptional case —
 * should not occur when the lifecycle rules are respected), the step continues
 * as if no assignment exists and the unit will be re-bucketed.
 */
final readonly class LoadExistingAssignmentStep implements ResolutionStep
{
    public function __construct(private AssignmentRepository $assignmentRepository)
    {
    }

    public function handle(ResolutionPayload $payload): bool
    {
        $assignment = $this->assignmentRepository->findAssignment(
            experimentKey: $payload->definition->key,
            unitType: $payload->definition->unitType,
            unitKey: $payload->unit->bucketingKey(),
        );

        if ($assignment === null) {
            return true;
        }

        $variant = $payload->definition->allocation->findVariantByKey($assignment->variantKey);

        if ($variant === null) {
            // Stored variant key is no longer in the allocation.
            // Treat as no assignment so the unit is re-bucketed.
            return true;
        }

        $payload->resolvedVariant = $variant;
        $payload->hasExistingAssignment = true;

        return true;
    }
}
