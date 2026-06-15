<?php

declare(strict_types=1);

namespace ABTests\Application\CommandHandlers;

use ABTests\Application\Commands\SetExperimentEnvironmentsCommand;
use ABTests\Domain\Events\ExperimentEnvironmentsUpdatedEvent;
use ABTests\Exceptions\ExperimentNotFound;
use ABTests\Infrastructure\Database\Models\AuditLogModel;
use ABTests\Infrastructure\Database\Models\ExperimentModel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

final readonly class SetExperimentEnvironmentsCommandHandler
{
    public function handle(SetExperimentEnvironmentsCommand $command): void
    {
        /** @var ExperimentModel|null $model */
        $model = ExperimentModel::query()->firstWhere('key', $command->experimentKey);

        if ($model === null) {
            throw new ExperimentNotFound("Experiment [$command->experimentKey] not found.");
        }

        $before = $model->allowed_environments;

        $model->update(['allowed_environments' => $command->allowedEnvironments]);

        AuditLogModel::query()->create([
            'actor_identifier' => $command->actorIdentifier,
            'actor_type'       => $command->actorType,
            'action'           => 'set_experiment_environments',
            'experiment_key'   => $command->experimentKey,
            'before_state'     => ['allowed_environments' => $before],
            'after_state'      => ['allowed_environments' => $command->allowedEnvironments],
            'occurred_at'      => Carbon::now(),
        ]);

        Event::dispatch(new ExperimentEnvironmentsUpdatedEvent(
            experimentKey: $command->experimentKey,
            allowedEnvironments: $command->allowedEnvironments,
            actorIdentifier: $command->actorIdentifier,
            actorType: $command->actorType,
        ));
    }
}
