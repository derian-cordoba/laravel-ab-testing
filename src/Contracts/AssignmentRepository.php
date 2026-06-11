<?php

declare(strict_types=1);

namespace ABTests\Contracts;

use ABTests\Values\Assignment;

/**
 * Persistence seam for sticky variant assignments. The default implementation
 * is database-backed (PostgreSQL); swap this binding for tests or alternative stores.
 */
interface AssignmentRepository
{
    /**
     * Return the persisted assignment for a unit on an experiment, or null if
     * the unit has not been assigned yet.
     */
    public function findAssignment(
        string $experimentKey,
        string $unitType,
        string $unitKey,
    ): ?Assignment;

    /**
     * Persist a new assignment. Implementations must be idempotent: a second
     * write for the same (experimentKey, unitType, unitKey) triple must not
     * overwrite the first (use INSERT … ON CONFLICT DO NOTHING or equivalent).
     */
    public function storeAssignment(Assignment $assignment): void;

    /**
     * Return the first live assignment found in the given layer for a unit.
     * Used by the layer-exclusion pipeline step to enforce mutual exclusion:
     * a unit enters at most one running experiment per layer.
     */
    public function findAssignmentByLayer(
        string $layer,
        string $unitType,
        string $unitKey,
    ): ?Assignment;
}
