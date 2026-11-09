<?php

declare(strict_types=1);

namespace ABTests\Application\Handlers;

use ABTests\Application\Commands\DisableFeatureFlagCommand;
use ABTests\Contracts\DomainEventDispatcher;
use ABTests\Contracts\FeatureFlagRepository;
use ABTests\Domain\FeatureFlag\FeatureFlagAggregate;

final readonly class DisableFeatureFlagCommandHandler
{
    public function __construct(
        private FeatureFlagRepository $featureFlagRepository,
        private DomainEventDispatcher $eventDispatcher,
    ) {
        //
    }

    public function handle(DisableFeatureFlagCommand $command): void
    {
        $record = $this->featureFlagRepository->findByKey($command->flagKey)
            ?? $this->featureFlagRepository->create(['key' => $command->flagKey, 'is_enabled' => true]);

        $aggregate = FeatureFlagAggregate::reconstitute($record);
        $aggregate->disable($command->actorIdentifier, $command->actorType);

        $this->featureFlagRepository->update($command->flagKey, $aggregate->pendingChanges());
        $this->eventDispatcher->dispatchAll($aggregate->pullEvents());
    }
}
