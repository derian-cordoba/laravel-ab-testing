<?php

declare(strict_types=1);

namespace ABTests\Application\CommandHandlers;

use ABTests\Application\Commands\ToggleFlagKillSwitchCommand;
use ABTests\Domain\Events\KillSwitchActivatedEvent;
use ABTests\Infrastructure\Database\Models\FeatureFlagStateModel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

final readonly class ToggleFlagKillSwitchCommandHandler
{
    public function handle(ToggleFlagKillSwitchCommand $command): void
    {
        FeatureFlagStateModel::query()->updateOrCreate(
            ['key' => $command->flagKey],
            ['killed_at' => $command->isKilled ? Carbon::now() : null],
        );

        Event::dispatch(new KillSwitchActivatedEvent(
            experimentKey: null,
            flagKey: $command->flagKey,
            activated: $command->isKilled,
            actorIdentifier: $command->actorIdentifier,
            actorType: $command->actorType,
        ));
    }
}
