<?php

declare(strict_types=1);

namespace ABTests\Application\Handlers;

use ABTests\Application\Commands\PauseExperimentCommand;
use ABTests\Contracts\AuditLogRepository;
use ABTests\Contracts\DomainEventDispatcher;
use ABTests\Contracts\ExperimentRepository;
use ABTests\Domain\Events\ExperimentPausedEvent;
use ABTests\Enums\ExperimentStatus;
use ABTests\Exceptions\InvalidStateTransition;

final readonly class PauseExperimentCommandHandler
{
    public function __construct(
        private ExperimentRepository $experimentRepository,
        private AuditLogRepository $auditLogRepository,
        private DomainEventDispatcher $eventDispatcher,
    ) {}

    public function handle(PauseExperimentCommand $command): void
    {
        $record = $this->experimentRepository->getByKey($command->experimentKey);

        $currentStatus = ExperimentStatus::from($record->status);

        if (! $currentStatus->canTransitionTo(ExperimentStatus::paused)) {
            throw new InvalidStateTransition($currentStatus, ExperimentStatus::paused);
        }

        $beforeState = ['status' => $record->status];

        $this->experimentRepository->update($command->experimentKey, ['status' => ExperimentStatus::paused->value]);

        $this->auditLogRepository->append(
            experimentKey: $command->experimentKey,
            action: 'pause',
            actorIdentifier: $command->actorIdentifier,
            actorType: $command->actorType,
            before: $beforeState,
            after: ['status' => ExperimentStatus::paused->value],
        );

        $this->eventDispatcher->dispatch(new ExperimentPausedEvent(
            experimentKey: $command->experimentKey,
            actorIdentifier: $command->actorIdentifier,
            actorType: $command->actorType,
        ));
    }
}
