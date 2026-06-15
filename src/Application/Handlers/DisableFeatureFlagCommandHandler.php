<?php

declare(strict_types=1);

namespace ABTests\Application\Handlers;

use ABTests\Application\Commands\DisableFeatureFlagCommand;
use ABTests\Contracts\FeatureFlagRepository;
use ABTests\Domain\Events\FeatureFlagDisabledEvent;
use Illuminate\Support\Facades\Event;

final readonly class DisableFeatureFlagCommandHandler
{
    public function __construct(
        private FeatureFlagRepository $featureFlagRepository,
    ) {
    }

    public function handle(DisableFeatureFlagCommand $command): void
    {
        $state = $this->featureFlagRepository->findByKey($command->flagKey);

        if ($state !== null) {
            $this->featureFlagRepository->update($command->flagKey, ['is_enabled' => false]);
        } else {
            $this->featureFlagRepository->create(['key' => $command->flagKey, 'is_enabled' => false]);
        }

        Event::dispatch(new FeatureFlagDisabledEvent(
            flagKey: $command->flagKey,
            actorIdentifier: $command->actorIdentifier,
            actorType: $command->actorType,
        ));
    }
}
