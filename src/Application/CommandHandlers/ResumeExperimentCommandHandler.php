<?php

declare(strict_types=1);

namespace ABTests\Application\CommandHandlers;

use ABTests\Application\Commands\ResumeExperimentCommand;
use ABTests\Domain\Events\ExperimentResumedEvent;
use ABTests\Enums\ExperimentStatus;
use ABTests\Exceptions\ExperimentNotFound;
use ABTests\Exceptions\InvalidStateTransition;
use ABTests\Infrastructure\Database\Models\AuditLogModel;
use ABTests\Infrastructure\Database\Models\ExperimentModel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

final readonly class ResumeExperimentCommandHandler
{
    public function handle(ResumeExperimentCommand $command): void
    {
        $model = ExperimentModel::query()->firstWhere('key', $command->experimentKey);

        if ($model === null) {
            throw new ExperimentNotFound($command->experimentKey);
        }

        $currentStatus = ExperimentStatus::from($model->status);

        if (! $currentStatus->canTransitionTo(ExperimentStatus::running)) {
            throw new InvalidStateTransition($currentStatus, ExperimentStatus::running);
        }

        $beforeState = ['status' => $model->status];

        $model->update(['status' => ExperimentStatus::running->value]);

        AuditLogModel::query()->create([
            'actor_identifier' => $command->actorIdentifier,
            'actor_type' => $command->actorType,
            'action' => 'resume',
            'experiment_key' => $command->experimentKey,
            'before_state' => $beforeState,
            'after_state' => ['status' => ExperimentStatus::running->value],
            'occurred_at' => Carbon::now(),
        ]);

        Event::dispatch(new ExperimentResumedEvent(
            experimentKey: $command->experimentKey,
            actorIdentifier: $command->actorIdentifier,
            actorType: $command->actorType,
        ));
    }
}
