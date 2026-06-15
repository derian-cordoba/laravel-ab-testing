<?php

declare(strict_types=1);

namespace ABTests\Application\CommandHandlers;

use ABTests\Application\Commands\DisableFeatureFlagCommand;
use ABTests\Domain\Events\FeatureFlagDisabledEvent;
use ABTests\Infrastructure\Database\Models\FeatureFlagStateModel;
use Illuminate\Support\Facades\Event;

final readonly class DisableFeatureFlagCommandHandler
{
    public function handle(DisableFeatureFlagCommand $command): void
    {
        FeatureFlagStateModel::query()->updateOrCreate(
            ['key' => $command->flagKey],
            ['is_enabled' => false],
        );

        Event::dispatch(new FeatureFlagDisabledEvent(
            flagKey: $command->flagKey,
            actorIdentifier: $command->actorIdentifier,
            actorType: $command->actorType,
        ));
    }
}
