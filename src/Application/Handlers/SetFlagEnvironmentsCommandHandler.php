<?php

declare(strict_types=1);

namespace ABTests\Application\Handlers;

use ABTests\Application\Commands\SetFlagEnvironmentsCommand;
use ABTests\Contracts\AuditLogRepository;
use ABTests\Contracts\FeatureFlagRepository;
use ABTests\Domain\Events\FeatureFlagEnvironmentsUpdatedEvent;
use Illuminate\Support\Facades\Event;

final readonly class SetFlagEnvironmentsCommandHandler
{
    public function __construct(
        private FeatureFlagRepository $featureFlagRepository,
        private AuditLogRepository $auditLogRepository,
    ) {}

    public function handle(SetFlagEnvironmentsCommand $command): void
    {
        $model = $this->featureFlagRepository->getByKey($command->flagKey);

        $before = $model->allowed_environments;

        $this->featureFlagRepository->update($command->flagKey, ['allowed_environments' => $command->allowedEnvironments]);

        $this->auditLogRepository->appendForFlag(
            flagKey: $command->flagKey,
            action: 'set_flag_environments',
            actorIdentifier: $command->actorIdentifier,
            actorType: $command->actorType,
            before: ['allowed_environments' => $before],
            after: ['allowed_environments' => $command->allowedEnvironments],
        );

        Event::dispatch(new FeatureFlagEnvironmentsUpdatedEvent(
            flagKey: $command->flagKey,
            allowedEnvironments: $command->allowedEnvironments,
            actorIdentifier: $command->actorIdentifier,
            actorType: $command->actorType,
        ));
    }
}
