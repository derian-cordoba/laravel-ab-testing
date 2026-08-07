<?php

declare(strict_types=1);

namespace ABTests\Application\Handlers;

use ABTests\Application\Commands\ArchiveExperimentCommand;
use ABTests\Contracts\AuditLogRepository;
use ABTests\Contracts\ExperimentRepository;
use ABTests\Domain\Experiment\ExperimentAggregate;

final readonly class ArchiveExperimentCommandHandler
{
    public function __construct(
        private ExperimentRepository $experimentRepository,
        private AuditLogRepository $auditLogRepository,
    ) {}

    public function handle(ArchiveExperimentCommand $command): void
    {
        $record = $this->experimentRepository->getByKey($command->experimentKey);
        $aggregate = ExperimentAggregate::reconstitute($record);
        $aggregate->archive($command->actorIdentifier, $command->actorType);

        $this->experimentRepository->update($command->experimentKey, $aggregate->pendingChanges());

        $this->auditLogRepository->append(
            experimentKey: $command->experimentKey,
            action: 'archive',
            actorIdentifier: $command->actorIdentifier,
            actorType: $command->actorType,
            before: $aggregate->beforeState(),
            after: $aggregate->pendingChanges(),
        );
    }
}
