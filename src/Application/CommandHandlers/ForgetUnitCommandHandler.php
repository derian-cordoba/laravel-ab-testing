<?php

declare(strict_types=1);

namespace ABTests\Application\CommandHandlers;

use ABTests\Application\Commands\ForgetUnitCommand;
use ABTests\Infrastructure\Database\Models\AuditLogModel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Executes the right-to-erasure request by purging all events and assignments
 * for the unit across every experiment. Rollup rows are NOT recomputed
 * immediately — they will converge on the next RefreshRollupsJob cycle via the
 * COUNT(DISTINCT unit_key) recount. The audit record itself is kept so there is
 * a tamper-evident trace that erasure occurred.
 */
final readonly class ForgetUnitCommandHandler
{
    public function handle(ForgetUnitCommand $command): array
    {
        $deletedEvents = DB::table('ab_testing_events')
            ->where('unit_type', $command->unitType)
            ->where('unit_key', $command->unitKey)
            ->delete();

        $deletedAssignments = DB::table('ab_testing_assignments')
            ->where('unit_type', $command->unitType)
            ->where('unit_key', $command->unitKey)
            ->delete();

        AuditLogModel::query()->create([
            'actor_identifier' => $command->actorIdentifier,
            'actor_type'       => $command->actorType,
            'action'           => 'forget_unit',
            'experiment_key'   => '*',
            'before_state'     => [
                'unit_type' => $command->unitType,
                'unit_key'  => $command->unitKey,
            ],
            'after_state' => [
                'deleted_events'      => $deletedEvents,
                'deleted_assignments' => $deletedAssignments,
            ],
            'occurred_at' => Carbon::now(),
        ]);

        return [
            'deleted_events'      => $deletedEvents,
            'deleted_assignments' => $deletedAssignments,
        ];
    }
}
