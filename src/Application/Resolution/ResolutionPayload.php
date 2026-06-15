<?php

declare(strict_types=1);

namespace ABTests\Application\Resolution;

use ABTests\Contracts\Bucketable;
use ABTests\Contracts\Variant;
use ABTests\Definitions\ExperimentDefinition;
use ABTests\Values\ExperimentState;

/**
 * Mutable state threaded through the resolution pipeline. Each step reads
 * and/or writes this object; when all steps complete, resolvedVariant holds
 * the assigned variant (or null if the unit was excluded by any step).
 *
 * The bucket position is computed once by the Resolver before the pipeline
 * starts, so steps that need it (traffic allocation, bucketing) use the same
 * deterministic value without re-hashing.
 */
final class ResolutionPayload
{
    /**
     * The variant assigned to the unit. Null until BucketStep or
     * LoadExistingAssignmentStep sets it. If null at pipeline end, the unit
     * is not assigned.
     */
    public ?Variant $resolvedVariant = null;

    /**
     * True once LoadExistingAssignmentStep finds a persisted assignment.
     * Downstream steps (layer-exclusion, bucket, persist) skip their work
     * when this flag is set, preserving sticky assignment semantics.
     */
    public bool $hasExistingAssignment = false;

    /**
     * @param float $bucketPosition Deterministic position in [0, 1) for this
     *                              unit on this experiment, computed by the
     *                              BucketingStrategy before the pipeline runs.
     */
    public function __construct(
        public readonly ExperimentDefinition $definition,
        public readonly Bucketable $unit,
        public readonly ExperimentState $state,
        public readonly float $bucketPosition,
    ) {
        //
    }
}
