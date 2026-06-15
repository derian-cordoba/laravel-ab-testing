<?php

declare(strict_types=1);

namespace ABTests\Application\CommandHandlers;

use ABTests\Application\Commands\PauseExperimentCommand;
use ABTests\Domain\Events\ExperimentPausedEvent;
use ABTests\Enums\ExperimentStatus;
use ABTests\Exceptions\ExperimentNotFound;
use ABTests\Exceptions\InvalidStateTransition;
use ABTests\Infrastructure\Database\Models\AuditLogModel;
use ABTests\Infrastructure\Database\Models\ExperimentModel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

final readonly class PauseExperimentCommandHandler
{
    public function handle(PauseExperimentCommand $command): void
    {
        $model = ExperimentModel::query()->firstWhere('key', $command->experimentKey);

        if ($model === null) {
            throw new ExperimentNotFound($command->experimentKey);
        }

        $currentStatus = ExperimentStatus::from($model->status);

        if (! $currentStatus->canTransitionTo(ExperimentStatus::paused)) {
            throw new InvalidStateTransition($currentStatus, ExperimentStatus::paused);
        }

        $beforeState = ['status' => $model->status];

        $model->update(['status' => ExperimentStatus::paused->value]);

        AuditLogModel::query()->create([
            'actor_identifier' => $command->actorIdentifier,
            'actor_type' => $command->actorType,
            'action' => 'pause',
            'experiment_key' => $command->experimentKey,
            'before_state' => $beforeState,
            'after_state' => ['status' => ExperimentStatus::paused->value],
            'occurred_at' => Carbon::now(),
        ]);

        Event::dispatch(new ExperimentPausedEvent(
            experimentKey: $command->experimentKey,
            actorIdentifier: $command->actorIdentifier,
            actorType: $command->actorType,
        ));
    }
}
