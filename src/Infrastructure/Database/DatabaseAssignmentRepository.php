<?php

declare(strict_types=1);

namespace ABTests\Infrastructure\Database;

use ABTests\Contracts\AssignmentRepository;
use ABTests\Infrastructure\Database\Models\AssignmentModel;
use ABTests\Values\Assignment;
use DateTimeImmutable;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Database-backed assignment repository. Uses the ab_testing_assignments table
 * with a composite primary key on (experiment_key, unit_type, unit_key).
 *
 * Idempotency is guaranteed at the database level: storeAssignment() uses an
 * INSERT … ON CONFLICT DO NOTHING / INSERT IGNORE strategy — the first write
 * wins and re-assignments are silently discarded without raising an exception.
 */
final class DatabaseAssignmentRepository implements AssignmentRepository
{
    public function findAssignment(
        string $experimentKey,
        string $unitType,
        string $unitKey,
    ): ?Assignment {
        $row = AssignmentModel::query()
            ->where('experiment_key', $experimentKey)
            ->where('unit_type', $unitType)
            ->where('unit_key', $unitKey)
            ->first();

        return $row !== null ? $this->hydrate($row) : null;
    }

    public function storeAssignment(Assignment $assignment): void
    {
        try {
            AssignmentModel::query()->insert([
                'experiment_key' => $assignment->experimentKey,
                'unit_type' => $assignment->unitType,
                'unit_key' => $assignment->unitKey,
                'variant_key' => $assignment->variantKey,
                'layer' => $assignment->layer,
                'assigned_at' => $assignment->assignedAt->format('Y-m-d H:i:s'),
            ]);
        } catch (UniqueConstraintViolationException) {
            // First write wins. A second assignment for the same unit on the
            // same experiment is silently ignored — idempotent by design.
        }
    }

    public function findAssignmentByLayer(
        string $layer,
        string $unitType,
        string $unitKey,
    ): ?Assignment {
        $row = AssignmentModel::query()
            ->where('layer', $layer)
            ->where('unit_type', $unitType)
            ->where('unit_key', $unitKey)
            ->first();

        return $row !== null ? $this->hydrate($row) : null;
    }

    private function hydrate(AssignmentModel $row): Assignment
    {
        return new Assignment(
            experimentKey: $row->experiment_key,
            unitType: $row->unit_type,
            unitKey: $row->unit_key,
            variantKey: $row->variant_key,
            layer: $row->layer,
            assignedAt: DateTimeImmutable::createFromInterface($row->assigned_at),
        );
    }
}
