<?php

declare(strict_types=1);

namespace ABTests\Application\CommandHandlers;

use ABTests\Application\Commands\SetFlagEnvironmentsCommand;
use ABTests\Domain\Events\FeatureFlagEnvironmentsUpdatedEvent;
use ABTests\Exceptions\FeatureFlagNotFound;
use ABTests\Infrastructure\Database\Models\AuditLogModel;
use ABTests\Infrastructure\Database\Models\FeatureFlagStateModel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

final readonly class SetFlagEnvironmentsCommandHandler
{
    public function handle(SetFlagEnvironmentsCommand $command): void
    {
        /** @var FeatureFlagStateModel|null $model */
        $model = FeatureFlagStateModel::query()->firstWhere('key', $command->flagKey);

        if ($model === null) {
            throw new FeatureFlagNotFound("Feature flag [$command->flagKey] not found.");
        }

        $before = $model->allowed_environments;

        $model->update(['allowed_environments' => $command->allowedEnvironments]);

        AuditLogModel::query()->create([
            'actor_identifier' => $command->actorIdentifier,
            'actor_type'       => $command->actorType,
            'action'           => 'set_flag_environments',
            'experiment_key'   => null,
            'before_state'     => ['flag_key' => $command->flagKey, 'allowed_environments' => $before],
            'after_state'      => ['flag_key' => $command->flagKey, 'allowed_environments' => $command->allowedEnvironments],
            'occurred_at'      => Carbon::now(),
        ]);

        Event::dispatch(new FeatureFlagEnvironmentsUpdatedEvent(
            flagKey: $command->flagKey,
            allowedEnvironments: $command->allowedEnvironments,
            actorIdentifier: $command->actorIdentifier,
            actorType: $command->actorType,
        ));
    }
}
