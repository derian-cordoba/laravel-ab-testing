<?php

declare(strict_types=1);

namespace ABTests\Application\CommandHandlers;

use ABTests\Application\Commands\ToggleFlagKillSwitchCommand;
use ABTests\Infrastructure\Database\Models\FeatureFlagStateModel;
use Illuminate\Support\Carbon;

final readonly class ToggleFlagKillSwitchCommandHandler
{
    public function handle(ToggleFlagKillSwitchCommand $command): void
    {
        FeatureFlagStateModel::query()->updateOrCreate(
            ['key' => $command->flagKey],
            ['killed_at' => $command->isKilled ? Carbon::now() : null],
        );
    }
}
