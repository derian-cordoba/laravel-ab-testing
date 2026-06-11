<?php

declare(strict_types=1);

namespace ABTests\Infrastructure;

use ABTests\Contracts\AssignmentRepository;
use ABTests\Values\Assignment;

/**
 * In-process assignment store backed by plain PHP arrays. Intended for unit
 * tests, local development, and the testing utilities phase (Experiments::fake).
 * Not suitable for production (assignments are lost between requests).
 */
final class InMemoryAssignmentRepository implements AssignmentRepository
{
    /** @var array<string, Assignment> Keyed by "experimentKey:unitType:unitKey". */
    private array $assignments = [];

    /** @var array<string, Assignment> Keyed by "layer:unitType:unitKey". */
    private array $layerIndex = [];

    public function findAssignment(
        string $experimentKey,
        string $unitType,
        string $unitKey,
    ): ?Assignment {
        return $this->assignments[$this->assignmentKey($experimentKey, $unitType, $unitKey)] ?? null;
    }

    public function storeAssignment(Assignment $assignment): void
    {
        $key = $this->assignmentKey(
            $assignment->experimentKey,
            $assignment->unitType,
            $assignment->unitKey,
        );

        // Idempotent: first write wins.
        if (isset($this->assignments[$key])) {
            return;
        }

        $this->assignments[$key] = $assignment;

        if ($assignment->layer !== null) {
            $layerKey = $this->layerKey($assignment->layer, $assignment->unitType, $assignment->unitKey);

            // First assignment in this layer for this unit wins.
            $this->layerIndex[$layerKey] ??= $assignment;
        }
    }

    public function findAssignmentByLayer(
        string $layer,
        string $unitType,
        string $unitKey,
    ): ?Assignment {
        return $this->layerIndex[$this->layerKey($layer, $unitType, $unitKey)] ?? null;
    }

    /** Remove all stored assignments. Useful between test cases. */
    public function flush(): void
    {
        $this->assignments = [];
        $this->layerIndex = [];
    }

    // MARK: Private methods

    private function assignmentKey(string $experimentKey, string $unitType, string $unitKey): string
    {
        return "$experimentKey:$unitType:$unitKey";
    }

    private function layerKey(string $layer, string $unitType, string $unitKey): string
    {
        return "$layer:$unitType:$unitKey";
    }
}
