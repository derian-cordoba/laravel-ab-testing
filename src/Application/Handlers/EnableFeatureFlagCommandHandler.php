<?php

declare(strict_types=1);

namespace ABTests\Application\Handlers;

use ABTests\Application\Commands\EnableFeatureFlagCommand;
use ABTests\Contracts\FeatureFlagRepository;
use ABTests\Domain\Events\FeatureFlagEnabledEvent;
use Illuminate\Support\Facades\Event;

final readonly class EnableFeatureFlagCommandHandler
{
    public function __construct(
        private FeatureFlagRepository $featureFlagRepository,
    ) {}

    public function handle(EnableFeatureFlagCommand $command): void
    {
        $state = $this->featureFlagRepository->findByKey($command->flagKey);

        if ($state !== null) {
            $this->featureFlagRepository->update($command->flagKey, ['is_enabled' => true]);
        } else {
            $this->featureFlagRepository->create(['key' => $command->flagKey, 'is_enabled' => true]);
        }

        Event::dispatch(new FeatureFlagEnabledEvent(
            flagKey: $command->flagKey,
            actorIdentifier: $command->actorIdentifier,
            actorType: $command->actorType,
        ));
    }
}
