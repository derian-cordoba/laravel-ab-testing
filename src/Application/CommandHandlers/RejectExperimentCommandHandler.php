<?php

declare(strict_types=1);

namespace ABTests\Application\CommandHandlers;

use ABTests\Application\Commands\RejectExperimentCommand;
use ABTests\Enums\ApprovalStatus;
use ABTests\Exceptions\ExperimentNotFound;
use ABTests\Infrastructure\Database\Models\AuditLogModel;
use ABTests\Infrastructure\Database\Models\ExperimentApprovalModel;
use ABTests\Infrastructure\Database\Models\ExperimentModel;
use Illuminate\Support\Carbon;

final readonly class RejectExperimentCommandHandler
{
    public function handle(RejectExperimentCommand $command): void
    {
        $model = ExperimentModel::query()->firstWhere('key', $command->experimentKey);

        if ($model === null) {
            throw new ExperimentNotFound($command->experimentKey);
        }

        /** @var ExperimentApprovalModel|null $approval */
        $approval = ExperimentApprovalModel::query()
            ->where('experiment_key', $command->experimentKey)
            ->where('status', ApprovalStatus::pending->value)
            ->latest()
            ->first();

        if ($approval !== null) {
            $approval->update([
                'status'           => ApprovalStatus::rejected->value,
                'reviewed_by'      => $command->actorIdentifier,
                'reviewed_by_type' => $command->actorType,
                'notes'            => $command->notes ?? $approval->notes,
                'reviewed_at'      => Carbon::now(),
            ]);
        }

        AuditLogModel::query()->create([
            'actor_identifier' => $command->actorIdentifier,
            'actor_type'       => $command->actorType,
            'action'           => 'reject',
            'experiment_key'   => $command->experimentKey,
            'before_state'     => ['approval_status' => ApprovalStatus::pending->value],
            'after_state'      => ['approval_status' => ApprovalStatus::rejected->value],
            'occurred_at'      => Carbon::now(),
        ]);
    }
}
