<?php

declare(strict_types=1);

namespace ABTests\Application\Handlers;

use ABTests\Application\Commands\CreateFeatureFlagCommand;
use ABTests\Contracts\AuditLogRepository;
use ABTests\Contracts\FeatureFlagRepository;

final readonly class CreateFeatureFlagCommandHandler
{
    public function __construct(
        private FeatureFlagRepository $featureFlagRepository,
        private AuditLogRepository $auditLogRepository,
    ) {}

    public function handle(CreateFeatureFlagCommand $command): void
    {
        $this->featureFlagRepository->create([
            'key' => $command->key,
            'is_enabled' => $command->isEnabled,
            'rollout_percentage' => $command->rolloutPercentage,
            'conditions' => null,
        ]);

        $this->auditLogRepository->appendForFlag(
            flagKey: $command->key,
            action: 'create_feature_flag',
            actorIdentifier: $command->actorIdentifier,
            actorType: $command->actorType,
            before: [],
            after: [
                'is_enabled' => $command->isEnabled,
                'rollout_percentage' => $command->rolloutPercentage,
            ],
        );
    }
}
