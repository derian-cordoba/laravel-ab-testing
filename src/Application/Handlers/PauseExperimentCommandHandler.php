<?php

declare(strict_types=1);

namespace ABTests\Application\Handlers;

use ABTests\Application\Commands\PauseExperimentCommand;
use ABTests\Contracts\AuditLogRepository;
use ABTests\Contracts\DomainEventDispatcher;
use ABTests\Contracts\ExperimentRepository;
use ABTests\Domain\Experiment\ExperimentAggregate;

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
        $aggregate = ExperimentAggregate::reconstitute($record);
        $aggregate->pause($command->actorIdentifier, $command->actorType);

        $this->experimentRepository->update($command->experimentKey, $aggregate->pendingChanges());

        $this->auditLogRepository->append(
            experimentKey: $command->experimentKey,
            action: 'pause',
            actorIdentifier: $command->actorIdentifier,
            actorType: $command->actorType,
            before: $aggregate->beforeState(),
            after: $aggregate->pendingChanges(),
        );

        $this->eventDispatcher->dispatchAll($aggregate->pullEvents());
    }
}
