<?php

declare(strict_types=1);

namespace ABTests\Application\Handlers;

use ABTests\Application\Commands\PauseExperimentCommand;
use ABTests\Contracts\AuditLogRepository;
use ABTests\Contracts\ExperimentRepository;
use ABTests\Domain\Events\ExperimentPausedEvent;
use ABTests\Enums\ExperimentStatus;
use ABTests\Exceptions\InvalidStateTransition;
use Illuminate\Support\Facades\Event;

final readonly class PauseExperimentCommandHandler
{
    public function __construct(
        private ExperimentRepository $experimentRepository,
        private AuditLogRepository $auditLogRepository,
    ) {
    }

    public function handle(PauseExperimentCommand $command): void
    {
        $model = $this->experimentRepository->getByKey($command->experimentKey);

        $currentStatus = ExperimentStatus::from($model->status);

        if (! $currentStatus->canTransitionTo(ExperimentStatus::paused)) {
            throw new InvalidStateTransition($currentStatus, ExperimentStatus::paused);
        }

        $beforeState = ['status' => $model->status];

        $model->update(['status' => ExperimentStatus::paused->value]);

        $this->auditLogRepository->append(
            experimentKey: $command->experimentKey,
            action: 'pause',
            actorIdentifier: $command->actorIdentifier,
            actorType: $command->actorType,
            before: $beforeState,
            after: ['status' => ExperimentStatus::paused->value],
        );

        Event::dispatch(new ExperimentPausedEvent(
            experimentKey: $command->experimentKey,
            actorIdentifier: $command->actorIdentifier,
            actorType: $command->actorType,
        ));
    }
}
