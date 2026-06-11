<?php

declare(strict_types=1);

namespace ABTests\Application\CommandHandlers;

use ABTests\Application\Commands\EnableFeatureFlagCommand;
use ABTests\Infrastructure\Database\Models\FeatureFlagStateModel;

final readonly class EnableFeatureFlagCommandHandler
{
    public function handle(EnableFeatureFlagCommand $command): void
    {
        FeatureFlagStateModel::query()->updateOrCreate(
            ['key' => $command->flagKey],
            ['is_enabled' => true],
        );
    }
}
