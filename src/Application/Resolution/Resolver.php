<?php

declare(strict_types=1);

namespace ABTests\Application\Resolution;

use ABTests\Application\Resolution\Contracts\ResolutionStep;
use ABTests\Contracts\Bucketable;
use ABTests\Contracts\BucketingStrategy;
use ABTests\Contracts\ExperimentStateRepository;
use ABTests\Contracts\ResolvesVariant;
use ABTests\Definitions\ExperimentDefinition;
use ABTests\Enums\EvaluationReason;
use ABTests\Values\EvaluationResult;

/**
 * Orchestrates the resolution pipeline. Given an ExperimentDefinition and a
 * Bucketable unit, it fetches the operational state, computes the deterministic
 * bucket position, and runs each step in order.
 *
 * Returns an EvaluationResult describing the resolved variant (or null) and the
 * reason the pipeline produced that result.
 *
 * Resolution is a pure function of its inputs: same definition + same unit +
 * same state → same variant. No side effects beyond what the steps inject.
 */
final readonly class Resolver implements ResolvesVariant
{
    /**
     * @param  list<ResolutionStep>  $steps  Ordered pipeline steps.
     */
    public function __construct(
        private BucketingStrategy $bucketingStrategy,
        private ExperimentStateRepository $stateRepository,
        private array $steps,
    ) {
        //
    }

    public function resolve(ExperimentDefinition $definition, Bucketable $unit, bool $dryRun = false): EvaluationResult
    {
        $state = $this->stateRepository->findState($definition->key);

        if ($state === null) {
            return new EvaluationResult(
                variant: null,
                reason: EvaluationReason::experimentNotRunning,
                eligible: false,
                assigned: false,
                exposed: false,
                bucket: 0,
                matchedCriterion: null,
            );
        }

        $bucketPosition = $this->bucketingStrategy->position($definition->key, $unit);
        $payload = new ResolutionPayload($definition, $unit, $state, $bucketPosition);
        $payload->dryRun = $dryRun;

        foreach ($this->steps as $step) {
            if (! $step->handle($payload)) {
                return new EvaluationResult(
                    variant: null,
                    reason: $payload->stopReason ?? EvaluationReason::experimentNotRunning,
                    eligible: ($payload->stopReason ?? EvaluationReason::experimentNotRunning)->isEligible(),
                    assigned: false,
                    exposed: false,
                    bucket: (int) floor($bucketPosition * 10000),
                    matchedCriterion: null,
                );
            }
        }

        $reason = $payload->stopReason ?? EvaluationReason::newAssignment;

        return new EvaluationResult(
            variant: $payload->resolvedVariant,
            reason: $reason,
            eligible: $reason->isEligible(),
            assigned: $payload->resolvedVariant !== null,
            exposed: false,
            bucket: (int) floor($bucketPosition * 10000),
            matchedCriterion: null,
        );
    }
}
