<?php

declare(strict_types=1);

namespace ABTests\Application\CommandHandlers;

use ABTests\Application\Commands\ArchiveExperimentCommand;
use ABTests\Enums\ExperimentStatus;
use ABTests\Exceptions\ExperimentNotFound;
use ABTests\Exceptions\InvalidStateTransition;
use ABTests\Infrastructure\Database\Models\AuditLogModel;
use ABTests\Infrastructure\Database\Models\ExperimentModel;
use Illuminate\Support\Carbon;

final readonly class ArchiveExperimentCommandHandler
{
    public function handle(ArchiveExperimentCommand $command): void
    {
        $model = ExperimentModel::query()->firstWhere('key', $command->experimentKey);

        if ($model === null) {
            throw new ExperimentNotFound($command->experimentKey);
        }

        $currentStatus = ExperimentStatus::from($model->status);

        if (! $currentStatus->canTransitionTo(ExperimentStatus::archived)) {
            throw new InvalidStateTransition($currentStatus, ExperimentStatus::archived);
        }

        $beforeState = ['status' => $model->status];

        $model->update(['status' => ExperimentStatus::archived->value]);

        AuditLogModel::query()->create([
            'actor_identifier' => $command->actorIdentifier,
            'actor_type' => $command->actorType,
            'action' => 'archive',
            'experiment_key' => $command->experimentKey,
            'before_state' => $beforeState,
            'after_state' => ['status' => ExperimentStatus::archived->value],
            'occurred_at' => Carbon::now(),
        ]);

    }
}
