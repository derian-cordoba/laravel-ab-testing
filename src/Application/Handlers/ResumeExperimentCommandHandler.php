<?php

declare(strict_types=1);

namespace ABTests\Application\Handlers;

use ABTests\Application\Commands\ResumeExperimentCommand;
use ABTests\Contracts\AuditLogRepository;
use ABTests\Contracts\DomainEventDispatcher;
use ABTests\Contracts\ExperimentRepository;
use ABTests\Domain\Events\ExperimentResumedEvent;
use ABTests\Enums\ExperimentStatus;
use ABTests\Exceptions\InvalidStateTransition;

final readonly class ResumeExperimentCommandHandler
{
    public function __construct(
        private ExperimentRepository $experimentRepository,
        private AuditLogRepository $auditLogRepository,
        private DomainEventDispatcher $eventDispatcher,
    ) {}

    public function handle(ResumeExperimentCommand $command): void
    {
        $record = $this->experimentRepository->getByKey($command->experimentKey);

        $currentStatus = ExperimentStatus::from($record->status);

        if (! $currentStatus->canTransitionTo(ExperimentStatus::running)) {
            throw new InvalidStateTransition($currentStatus, ExperimentStatus::running);
        }

        $beforeState = ['status' => $record->status];

        $this->experimentRepository->update($command->experimentKey, ['status' => ExperimentStatus::running->value]);

        $this->auditLogRepository->append(
            experimentKey: $command->experimentKey,
            action: 'resume',
            actorIdentifier: $command->actorIdentifier,
            actorType: $command->actorType,
            before: $beforeState,
            after: ['status' => ExperimentStatus::running->value],
        );

        $this->eventDispatcher->dispatch(new ExperimentResumedEvent(
            experimentKey: $command->experimentKey,
            actorIdentifier: $command->actorIdentifier,
            actorType: $command->actorType,
        ));
    }
}
