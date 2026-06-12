<?php

declare(strict_types=1);

namespace ABTests\Application\CommandHandlers;

use ABTests\Application\Commands\ApproveExperimentCommand;
use ABTests\Enums\ApprovalStatus;
use ABTests\Exceptions\ExperimentNotFound;
use ABTests\Infrastructure\Database\Models\AuditLogModel;
use ABTests\Infrastructure\Database\Models\ExperimentApprovalModel;
use ABTests\Infrastructure\Database\Models\ExperimentModel;
use Illuminate\Support\Carbon;

final readonly class ApproveExperimentCommandHandler
{
    public function handle(ApproveExperimentCommand $command): void
    {
        $model = ExperimentModel::query()->firstWhere('key', $command->experimentKey);

        if ($model === null) {
            throw new ExperimentNotFound($command->experimentKey);
        }

        // Find the latest pending approval and mark it approved.
        /** @var ExperimentApprovalModel|null $approval */
        $approval = ExperimentApprovalModel::query()
            ->where('experiment_key', $command->experimentKey)
            ->where('status', ApprovalStatus::pending->value)
            ->latest()
            ->first();

        if ($approval !== null) {
            $approval->update([
                'status'           => ApprovalStatus::approved->value,
                'reviewed_by'      => $command->actorIdentifier,
                'reviewed_by_type' => $command->actorType,
                'notes'            => $command->notes ?? $approval->notes,
                'reviewed_at'      => Carbon::now(),
            ]);
        } else {
            // No pending request found — create a direct approval (admin override).
            ExperimentApprovalModel::query()->create([
                'experiment_key'    => $command->experimentKey,
                'status'            => ApprovalStatus::approved->value,
                'requested_by'      => $command->actorIdentifier,
                'requested_by_type' => $command->actorType,
                'reviewed_by'       => $command->actorIdentifier,
                'reviewed_by_type'  => $command->actorType,
                'notes'             => $command->notes,
                'reviewed_at'       => Carbon::now(),
            ]);
        }

        AuditLogModel::query()->create([
            'actor_identifier' => $command->actorIdentifier,
            'actor_type'       => $command->actorType,
            'action'           => 'approve',
            'experiment_key'   => $command->experimentKey,
            'before_state'     => ['approval_status' => ApprovalStatus::pending->value],
            'after_state'      => ['approval_status' => ApprovalStatus::approved->value],
            'occurred_at'      => Carbon::now(),
        ]);
    }
}
