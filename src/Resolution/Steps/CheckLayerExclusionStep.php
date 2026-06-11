<?php

declare(strict_types=1);

namespace ABTests\Resolution\Steps;

use ABTests\Contracts\AssignmentRepository;
use ABTests\Resolution\Contracts\ResolutionStep;
use ABTests\Resolution\ResolutionPayload;

/**
 * Gate 5 — layer mutual exclusion. If this experiment belongs to a named layer,
 * a unit may only participate in one running experiment within that layer at a
 * time. Units already assigned to a different experiment in the same layer are
 * excluded from this one.
 *
 * Skipped entirely when:
 *   • the experiment has no layer, or
 *   • the unit already has a sticky assignment for this experiment (handled by
 *     LoadExistingAssignmentStep), meaning they are already the same experiment.
 */
final readonly class CheckLayerExclusionStep implements ResolutionStep
{
    public function __construct(private AssignmentRepository $assignmentRepository)
    {
    }

    public function handle(ResolutionPayload $payload): bool
    {
        // Already in this experiment — mutual exclusion does not apply.
        if ($payload->hasExistingAssignment) {
            return true;
        }

        $layer = $payload->definition->layer;

        if ($layer === null) {
            return true;
        }

        $existingLayerAssignment = $this->assignmentRepository->findAssignmentByLayer(
            layer: $layer,
            unitType: $payload->definition->unitType,
            unitKey: $payload->unit->bucketingKey(),
        );

        if ($existingLayerAssignment === null) {
            return true;
        }

        // A different experiment in the same layer owns this unit.
        return $existingLayerAssignment->experimentKey === $payload->definition->key;
    }
}
