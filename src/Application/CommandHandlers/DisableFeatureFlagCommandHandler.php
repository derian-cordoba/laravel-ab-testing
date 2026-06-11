<?php

declare(strict_types=1);

namespace ABTests\Application\CommandHandlers;

use ABTests\Application\Commands\DisableFeatureFlagCommand;
use ABTests\Infrastructure\Database\Models\FeatureFlagStateModel;

final readonly class DisableFeatureFlagCommandHandler
{
    public function handle(DisableFeatureFlagCommand $command): void
    {
        FeatureFlagStateModel::query()->updateOrCreate(
            ['key' => $command->flagKey],
            ['is_enabled' => false],
        );
    }
}
