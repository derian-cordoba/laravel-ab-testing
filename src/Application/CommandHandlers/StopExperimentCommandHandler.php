<?php

declare(strict_types=1);

namespace ABTests\Application\CommandHandlers;

use ABTests\Application\Commands\StopExperimentCommand;
use ABTests\Domain\Events\ExperimentStoppedEvent;
use ABTests\Enums\ExperimentStatus;
use ABTests\Exceptions\ExperimentNotFound;
use ABTests\Exceptions\InvalidStateTransition;
use ABTests\Infrastructure\Database\Models\AuditLogModel;
use ABTests\Infrastructure\Database\Models\ExperimentModel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

final readonly class StopExperimentCommandHandler
{
    public function handle(StopExperimentCommand $command): void
    {
        $model = ExperimentModel::query()->firstWhere('key', $command->experimentKey);

        if ($model === null) {
            throw new ExperimentNotFound($command->experimentKey);
        }

        $currentStatus = ExperimentStatus::from($model->status);

        if (! $currentStatus->canTransitionTo(ExperimentStatus::completed)) {
            throw new InvalidStateTransition($currentStatus, ExperimentStatus::completed);
        }

        $beforeState = ['status' => $model->status];

        $model->update([
            'status' => ExperimentStatus::completed->value,
            'stopped_at' => Carbon::now(),
        ]);

        AuditLogModel::query()->create([
            'actor_identifier' => $command->actorIdentifier,
            'actor_type' => $command->actorType,
            'action' => 'stop',
            'experiment_key' => $command->experimentKey,
            'before_state' => $beforeState,
            'after_state' => ['status' => ExperimentStatus::completed->value],
            'occurred_at' => Carbon::now(),
        ]);

        Event::dispatch(new ExperimentStoppedEvent(
            experimentKey: $command->experimentKey,
            actorIdentifier: $command->actorIdentifier,
            actorType: $command->actorType,
        ));
    }
}
