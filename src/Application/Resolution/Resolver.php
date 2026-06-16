<?php

declare(strict_types=1);

namespace ABTests\Application\Resolution;

use ABTests\Contracts\Bucketable;
use ABTests\Contracts\BucketingStrategy;
use ABTests\Contracts\ExperimentStateRepository;
use ABTests\Contracts\Variant;
use ABTests\Definitions\ExperimentDefinition;
use ABTests\Contracts\ResolvesVariant;
use ABTests\Application\Resolution\Contracts\ResolutionStep;

/**
 * Orchestrates the resolution pipeline. Given an ExperimentDefinition and a
 * Bucketable unit, it fetches the operational state, computes the deterministic
 * bucket position, and runs each step in order.
 *
 * Returns the resolved Variant, or null when any step short-circuits (the unit
 * is not eligible, excluded by segment, held out, or layer-excluded).
 *
 * Resolution is a pure function of its inputs: same definition + same unit +
 * same state → same variant. No side effects beyond what the steps inject.
 */
final readonly class Resolver implements ResolvesVariant
{
    /**
     * @param list<ResolutionStep> $steps Ordered pipeline steps.
     */
    public function __construct(
        private BucketingStrategy $bucketingStrategy,
        private ExperimentStateRepository $stateRepository,
        private array $steps,
    ) {
        //
    }

    public function resolve(ExperimentDefinition $definition, Bucketable $unit): ?Variant
    {
        $state = $this->stateRepository->findState($definition->key);

        if ($state === null) {
            return null;
        }

        $bucketPosition = $this->bucketingStrategy->position($definition->key, $unit);
        $payload = new ResolutionPayload($definition, $unit, $state, $bucketPosition);

        foreach ($this->steps as $step) {
            if (! $step->handle($payload)) {
                return null;
            }
        }

        return $payload->resolvedVariant;
    }
}
