<?php

declare(strict_types=1);

namespace ABTests\Application\Handlers;

use ABTests\Application\Commands\SetExperimentEnvironmentsCommand;
use ABTests\Contracts\AuditLogRepository;
use ABTests\Contracts\DomainEventDispatcher;
use ABTests\Contracts\ExperimentRepository;
use ABTests\Domain\Events\ExperimentEnvironmentsUpdatedEvent;

final readonly class SetExperimentEnvironmentsCommandHandler
{
    public function __construct(
        private ExperimentRepository $experimentRepository,
        private AuditLogRepository $auditLogRepository,
        private DomainEventDispatcher $eventDispatcher,
    ) {}

    public function handle(SetExperimentEnvironmentsCommand $command): void
    {
        $record = $this->experimentRepository->getByKey($command->experimentKey);

        $before = $record->allowedEnvironments;

        $this->experimentRepository->update($command->experimentKey, ['allowed_environments' => $command->allowedEnvironments]);

        $this->auditLogRepository->append(
            experimentKey: $command->experimentKey,
            action: 'set_experiment_environments',
            actorIdentifier: $command->actorIdentifier,
            actorType: $command->actorType,
            before: ['allowed_environments' => $before],
            after: ['allowed_environments' => $command->allowedEnvironments],
        );

        $this->eventDispatcher->dispatch(new ExperimentEnvironmentsUpdatedEvent(
            experimentKey: $command->experimentKey,
            allowedEnvironments: $command->allowedEnvironments,
            actorIdentifier: $command->actorIdentifier,
            actorType: $command->actorType,
        ));
    }
}
