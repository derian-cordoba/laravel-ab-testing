<?php

declare(strict_types=1);

namespace ABTests\Application\CommandHandlers;

use ABTests\Application\Commands\ToggleKillSwitchCommand;
use ABTests\Domain\Events\KillSwitchActivatedEvent;
use ABTests\Exceptions\ExperimentNotFound;
use ABTests\Infrastructure\Database\Models\AuditLogModel;
use ABTests\Infrastructure\Database\Models\ExperimentModel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

final readonly class ToggleKillSwitchCommandHandler
{
    public function handle(ToggleKillSwitchCommand $command): void
    {
        $model = ExperimentModel::query()->firstWhere('key', $command->experimentKey);

        if ($model === null) {
            throw new ExperimentNotFound($command->experimentKey);
        }

        $beforeState = ['is_killed' => $model->is_killed];

        $model->update([
            'is_killed' => $command->isKilled,
            'killed_at' => $command->isKilled ? Carbon::now() : null,
        ]);

        AuditLogModel::query()->create([
            'actor_identifier' => $command->actorIdentifier,
            'actor_type' => $command->actorType,
            'action' => 'kill',
            'experiment_key' => $command->experimentKey,
            'before_state' => $beforeState,
            'after_state' => ['is_killed' => $command->isKilled],
            'occurred_at' => Carbon::now(),
        ]);

        Event::dispatch(new KillSwitchActivatedEvent(
            experimentKey: $command->experimentKey,
            flagKey: null,
            activated: $command->isKilled,
            actorIdentifier: $command->actorIdentifier,
            actorType: $command->actorType,
        ));
    }
}
