<?php

declare(strict_types=1);

namespace ABTests\Application\CommandHandlers;

use ABTests\Application\Commands\StartExperimentCommand;
use ABTests\Enums\ExperimentStatus;
use ABTests\Exceptions\ExperimentNotFound;
use ABTests\Exceptions\InvalidStateTransition;
use ABTests\Infrastructure\Database\Models\AuditLogModel;
use ABTests\Infrastructure\Database\Models\ExperimentModel;
use Illuminate\Support\Carbon;

final readonly class StartExperimentCommandHandler
{
    public function handle(StartExperimentCommand $command): void
    {
        $model = ExperimentModel::query()->firstWhere('key', $command->experimentKey);

        if ($model === null) {
            throw new ExperimentNotFound($command->experimentKey);
        }

        $currentStatus = ExperimentStatus::from($model->status);

        if (! $currentStatus->canTransitionTo(ExperimentStatus::running)) {
            throw new InvalidStateTransition($currentStatus, ExperimentStatus::running);
        }

        $beforeState = ['status' => $model->status, 'started_at' => $model->started_at];
        $trafficPercentage = $model->traffic_percentage > 0 ? $model->traffic_percentage : 100;

        $model->update([
            'status' => ExperimentStatus::running->value,
            'traffic_percentage' => $trafficPercentage,
            'started_at' => $model->started_at ?? Carbon::now(),
        ]);

        AuditLogModel::query()->create([
            'actor_identifier' => $command->actorIdentifier,
            'actor_type' => $command->actorType,
            'action' => 'start',
            'experiment_key' => $command->experimentKey,
            'before_state' => $beforeState,
            'after_state' => [
                'status' => ExperimentStatus::running->value,
                'traffic_percentage' => $trafficPercentage,
            ],
            'occurred_at' => Carbon::now(),
        ]);

    }
}
