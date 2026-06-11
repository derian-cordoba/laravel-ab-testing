<?php

declare(strict_types=1);

namespace ABTests\Application\CommandHandlers;

use ABTests\Application\Commands\RampTrafficCommand;
use ABTests\Exceptions\ExperimentNotFound;
use ABTests\Infrastructure\Database\Models\AuditLogModel;
use ABTests\Infrastructure\Database\Models\ExperimentModel;
use Illuminate\Support\Carbon;

final readonly class RampTrafficCommandHandler
{
    public function handle(RampTrafficCommand $command): void
    {
        $model = ExperimentModel::query()->firstWhere('key', $command->experimentKey);

        if ($model === null) {
            throw new ExperimentNotFound($command->experimentKey);
        }

        $percentage = max(0, min(100, $command->trafficPercentage));
        $beforeState = ['traffic_percentage' => $model->traffic_percentage];

        $model->update(['traffic_percentage' => $percentage]);

        AuditLogModel::query()->create([
            'actor_identifier' => $command->actorIdentifier,
            'actor_type' => $command->actorType,
            'action' => 'ramp_traffic',
            'experiment_key' => $command->experimentKey,
            'before_state' => $beforeState,
            'after_state' => ['traffic_percentage' => $percentage],
            'occurred_at' => Carbon::now(),
        ]);

    }
}
