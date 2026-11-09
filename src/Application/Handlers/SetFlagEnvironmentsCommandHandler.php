<?php

declare(strict_types=1);

namespace ABTests\Application\Handlers;

use ABTests\Application\Commands\SetFlagEnvironmentsCommand;
use ABTests\Contracts\AuditLogRepository;
use ABTests\Contracts\DomainEventDispatcher;
use ABTests\Contracts\FeatureFlagRepository;
use ABTests\Domain\FeatureFlag\FeatureFlagAggregate;

final readonly class SetFlagEnvironmentsCommandHandler
{
    public function __construct(
        private FeatureFlagRepository $featureFlagRepository,
        private AuditLogRepository $auditLogRepository,
        private DomainEventDispatcher $eventDispatcher,
    ) {}

    public function handle(SetFlagEnvironmentsCommand $command): void
    {
        $record = $this->featureFlagRepository->getByKey($command->flagKey);

        $aggregate = FeatureFlagAggregate::reconstitute($record);
        $aggregate->setEnvironments($command->allowedEnvironments, $command->actorIdentifier, $command->actorType);

        $this->featureFlagRepository->update($command->flagKey, $aggregate->pendingChanges());

        $this->auditLogRepository->appendForFlag(
            flagKey: $command->flagKey,
            action: 'set_flag_environments',
            actorIdentifier: $command->actorIdentifier,
            actorType: $command->actorType,
            before: $aggregate->beforeState(),
            after: $aggregate->pendingChanges(),
        );

        $this->eventDispatcher->dispatchAll($aggregate->pullEvents());
    }
}
