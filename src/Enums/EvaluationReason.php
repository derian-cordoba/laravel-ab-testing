<?php

declare(strict_types=1);

namespace ABTests\Enums;

/**
 * Why the resolution pipeline produced its result for a given unit. Each case
 * maps to a specific gate in the pipeline that either accepted or rejected the
 * unit, or to the assignment path that was taken on success.
 *
 * Failure reasons (variant === null):
 *   experimentNotRunning       — experiment status is not "running"
 *   experimentKilled           — experiment is running but its kill switch is on
 *   environmentRestricted      — current app environment is not in the allowed list
 *   notInAudience              — unit did not match the experiment's audience segment
 *   outsideTrafficAllocation   — unit's bucket position is beyond the traffic slice
 *   layerExcluded              — unit is already assigned to another experiment in the same layer
 *
 * Success reasons (variant !== null):
 *   stickyAssignment           — a persisted assignment was loaded; unit was already bucketed
 *   newAssignment              — unit was freshly bucketed and a new assignment was written
 *   override                   — variant was forced by a test double or QA override
 *
 * No-op reasons (used by peek() / assignment() when no full pipeline is run):
 *   noAssignment               — peek() found no existing assignment for the unit
 */
enum EvaluationReason: string
{
    case experimentNotRunning = 'experiment_not_running';
    case experimentKilled = 'experiment_killed';
    case environmentRestricted = 'environment_restricted';
    case notInAudience = 'not_in_audience';
    case outsideTrafficAllocation = 'outside_traffic_allocation';
    case layerExcluded = 'layer_excluded';
    case stickyAssignment = 'sticky_assignment';
    case newAssignment = 'new_assignment';
    case override = 'override';
    case noAssignment = 'no_assignment';

    /**
     * Whether this reason represents a successful variant assignment.
     */
    public function isAssigned(): bool
    {
        return match ($this) {
            self::stickyAssignment,
            self::newAssignment,
            self::override => true,
            default => false,
        };
    }

    /**
     * Whether the unit was eligible (passed all targeting and traffic gates)
     * regardless of whether it was ultimately assigned.
     */
    public function isEligible(): bool
    {
        return match ($this) {
            self::layerExcluded,
            self::stickyAssignment,
            self::newAssignment,
            self::override => true,
            default => false,
        };
    }
}
