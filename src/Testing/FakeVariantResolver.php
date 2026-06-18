<?php

declare(strict_types=1);

namespace ABTests\Testing;

use ABTests\Contracts\AssignmentRepository;
use ABTests\Contracts\Bucketable;
use ABTests\Contracts\ResolvesVariant;
use ABTests\Definitions\ExperimentDefinition;
use ABTests\Enums\EvaluationReason;
use ABTests\Values\Assignment;
use ABTests\Values\EvaluationResult;
use DateTimeImmutable;

/**
 * A drop-in replacement for the real Resolver that returns explicitly forced
 * variants instead of running the full resolution pipeline. Any experiment key
 * that has not been forced returns an EvaluationResult with variant === null,
 * so tests control exactly which experiments appear to be active.
 *
 * When a forced variant is returned, the assignment is also stored in the
 * provided repository so that subsequent Experiments::track() calls can locate
 * the assignment — matching the behavior of the real resolution pipeline.
 *
 * Use via FakeExperiments::boot() — do not instantiate directly in tests.
 */
final class FakeVariantResolver implements ResolvesVariant
{
    /** @var array<string, \ABTests\Contracts\Variant> experiment_key → forced Variant */
    private array $forcedVariants = [];

    public function __construct(
        private readonly AssignmentRepository $assignmentRepository,
    ) {
        //
    }

    public function force(string $experimentKey, \ABTests\Contracts\Variant $variant): void
    {
        $this->forcedVariants[$experimentKey] = $variant;
    }

    public function remove(string $experimentKey): void
    {
        unset($this->forcedVariants[$experimentKey]);
    }

    public function reset(): void
    {
        $this->forcedVariants = [];
    }

    public function resolve(ExperimentDefinition $definition, Bucketable $unit, bool $dryRun = false): EvaluationResult
    {
        $variant = $this->forcedVariants[$definition->key] ?? null;

        if ($variant === null) {
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

        if (! $dryRun) {
            // Store the assignment so track() calls can look it up.
            $this->assignmentRepository->storeAssignment(new Assignment(
                experimentKey: $definition->key,
                unitType:      $definition->unitType,
                unitKey:       $unit->bucketingKey(),
                variantKey:    $variant->key(),
                layer:         $definition->layer,
                assignedAt:    new DateTimeImmutable(),
            ));
        }

        return new EvaluationResult(
            variant: $variant,
            reason: EvaluationReason::override,
            eligible: true,
            assigned: true,
            exposed: false,
            bucket: 0,
            matchedCriterion: null,
        );
    }
}
