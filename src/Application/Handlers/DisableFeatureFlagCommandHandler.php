<?php

declare(strict_types=1);

namespace ABTests\Application\Handlers;

use ABTests\Application\Commands\DisableFeatureFlagCommand;
use ABTests\Contracts\DomainEventDispatcher;
use ABTests\Contracts\FeatureFlagRepository;
use ABTests\Domain\Events\FeatureFlagDisabledEvent;

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
        $state = $this->featureFlagRepository->findByKey($command->flagKey);

        if ($state !== null) {
            $this->featureFlagRepository->update($command->flagKey, ['is_enabled' => false]);
        } else {
            $this->featureFlagRepository->create(['key' => $command->flagKey, 'is_enabled' => false]);
        }

        $this->eventDispatcher->dispatch(new FeatureFlagDisabledEvent(
            flagKey: $command->flagKey,
            actorIdentifier: $command->actorIdentifier,
            actorType: $command->actorType,
        ));
    }
}
