<?php

declare(strict_types=1);

namespace ABTests\Application\CommandHandlers;

use ABTests\Application\Commands\EnableFeatureFlagCommand;
use ABTests\Domain\Events\FeatureFlagEnabledEvent;
use ABTests\Infrastructure\Database\Models\FeatureFlagStateModel;
use Illuminate\Support\Facades\Event;

final readonly class EnableFeatureFlagCommandHandler
{
    public function handle(EnableFeatureFlagCommand $command): void
    {
        FeatureFlagStateModel::query()->updateOrCreate(
            ['key' => $command->flagKey],
            ['is_enabled' => true],
        );

        Event::dispatch(new FeatureFlagEnabledEvent(
            flagKey: $command->flagKey,
            actorIdentifier: $command->actorIdentifier,
            actorType: $command->actorType,
        ));
    }
}
