<?php

declare(strict_types=1);

namespace ABTests\Application\Handlers;

use ABTests\Application\Commands\EnableFeatureFlagCommand;
use ABTests\Contracts\DomainEventDispatcher;
use ABTests\Contracts\FeatureFlagRepository;
use ABTests\Domain\FeatureFlag\FeatureFlagAggregate;

final readonly class EnableFeatureFlagCommandHandler
{
    public function __construct(
        private FeatureFlagRepository $featureFlagRepository,
        private DomainEventDispatcher $eventDispatcher,
    ) {
        //
    }

    public function handle(EnableFeatureFlagCommand $command): void
    {
        $record = $this->featureFlagRepository->findByKey($command->flagKey)
            ?? $this->featureFlagRepository->create(['key' => $command->flagKey, 'is_enabled' => false]);

        $aggregate = FeatureFlagAggregate::reconstitute($record);
        $aggregate->enable($command->actorIdentifier, $command->actorType);

        $this->featureFlagRepository->update($command->flagKey, $aggregate->pendingChanges());
        $this->eventDispatcher->dispatchAll($aggregate->pullEvents());
    }
}
