<?php

declare(strict_types=1);

namespace ABTests\Application\Handlers;

use ABTests\Application\Commands\ToggleFlagKillSwitchCommand;
use ABTests\Contracts\FeatureFlagRepository;
use ABTests\Domain\Events\KillSwitchActivatedEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

final readonly class ToggleFlagKillSwitchCommandHandler
{
    public function __construct(
        private FeatureFlagRepository $featureFlagRepository,
    ) {}

    public function handle(ToggleFlagKillSwitchCommand $command): void
    {
        $state = $this->featureFlagRepository->findByKey($command->flagKey);
        $attributes = ['killed_at' => $command->isKilled ? Carbon::now() : null];

        if ($state !== null) {
            $this->featureFlagRepository->update($command->flagKey, $attributes);
        } else {
            $this->featureFlagRepository->create(array_merge(['key' => $command->flagKey], $attributes));
        }

        Event::dispatch(new KillSwitchActivatedEvent(
            experimentKey: null,
            flagKey: $command->flagKey,
            activated: $command->isKilled,
            actorIdentifier: $command->actorIdentifier,
            actorType: $command->actorType,
        ));
    }
}
