<?php

declare(strict_types=1);

namespace ABTests\Application\Handlers;

use ABTests\Application\Commands\ToggleFlagKillSwitchCommand;
use ABTests\Contracts\DomainEventDispatcher;
use ABTests\Contracts\FeatureFlagRepository;
use ABTests\Domain\FeatureFlag\FeatureFlagAggregate;

final readonly class ToggleFlagKillSwitchCommandHandler
{
    public function __construct(
        private FeatureFlagRepository $featureFlagRepository,
        private DomainEventDispatcher $eventDispatcher,
    ) {}

    public function handle(ToggleFlagKillSwitchCommand $command): void
    {
        $record = $this->featureFlagRepository->findByKey($command->flagKey)
            ?? $this->featureFlagRepository->create(['key' => $command->flagKey]);

        $aggregate = FeatureFlagAggregate::reconstitute($record);

        if ($command->isKilled) {
            $aggregate->activateKillSwitch($command->actorIdentifier, $command->actorType);
        } else {
            $aggregate->deactivateKillSwitch($command->actorIdentifier, $command->actorType);
        }

        $this->featureFlagRepository->update($command->flagKey, $aggregate->pendingChanges());
        $this->eventDispatcher->dispatchAll($aggregate->pullEvents());
    }
}
