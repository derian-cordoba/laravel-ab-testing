<?php

declare(strict_types=1);

namespace ABTests\Application\CommandHandlers;

use ABTests\Application\Commands\RequestApprovalCommand;
use ABTests\Enums\ApprovalStatus;
use ABTests\Exceptions\ExperimentNotFound;
use ABTests\Infrastructure\Database\Models\AuditLogModel;
use ABTests\Infrastructure\Database\Models\ExperimentApprovalModel;
use ABTests\Infrastructure\Database\Models\ExperimentModel;
use Illuminate\Support\Carbon;

final readonly class RequestApprovalCommandHandler
{
    public function handle(RequestApprovalCommand $command): ExperimentApprovalModel
    {
        $model = ExperimentModel::query()->firstWhere('key', $command->experimentKey);

        if ($model === null) {
            throw new ExperimentNotFound($command->experimentKey);
        }

        /** @var ExperimentApprovalModel $approval */
        $approval = ExperimentApprovalModel::query()->create([
            'experiment_key'    => $command->experimentKey,
            'status'            => ApprovalStatus::pending->value,
            'requested_by'      => $command->actorIdentifier,
            'requested_by_type' => $command->actorType,
            'notes'             => $command->notes,
        ]);

        AuditLogModel::query()->create([
            'actor_identifier' => $command->actorIdentifier,
            'actor_type'       => $command->actorType,
            'action'           => 'request_approval',
            'experiment_key'   => $command->experimentKey,
            'before_state'     => [],
            'after_state'      => ['approval_status' => ApprovalStatus::pending->value],
            'occurred_at'      => Carbon::now(),
        ]);

        return $approval;
    }
}
