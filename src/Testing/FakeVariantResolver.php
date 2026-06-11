<?php

declare(strict_types=1);

namespace ABTests\Testing;

use ABTests\Contracts\AssignmentRepository;
use ABTests\Contracts\Bucketable;
use ABTests\Contracts\ResolvesVariant;
use ABTests\Contracts\Variant;
use ABTests\Definitions\ExperimentDefinition;
use ABTests\Values\Assignment;
use DateTimeImmutable;

/**
 * A drop-in replacement for the real Resolver that returns explicitly forced
 * variants instead of running the full resolution pipeline. Any experiment key
 * that has not been forced returns null, so tests control exactly which
 * experiments appear to be active.
 *
 * When a forced variant is returned, the assignment is also stored in the
 * provided repository so that subsequent Experiments::track() calls can locate
 * the assignment — matching the behavior of the real resolution pipeline.
 *
 * Use via FakeExperiments::boot() — do not instantiate directly in tests.
 */
final class FakeVariantResolver implements ResolvesVariant
{
    /** @var array<string, Variant> experiment_key → forced Variant */
    private array $forcedVariants = [];

    public function __construct(
        private readonly AssignmentRepository $assignmentRepository,
    ) {
        //
    }

    public function force(string $experimentKey, Variant $variant): void
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

    public function resolve(ExperimentDefinition $definition, Bucketable $unit): ?Variant
    {
        $variant = $this->forcedVariants[$definition->key] ?? null;

        if ($variant !== null) {
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

        return $variant;
    }
}
