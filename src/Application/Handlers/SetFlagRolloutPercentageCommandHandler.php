<?php

declare(strict_types=1);

namespace ABTests\Application\Handlers;

use ABTests\Application\Commands\SetFlagRolloutPercentageCommand;
use ABTests\Contracts\FeatureFlagRepository;

final readonly class SetFlagRolloutPercentageCommandHandler
{
    public function __construct(
        private FeatureFlagRepository $featureFlagRepository,
    ) {}

    public function handle(SetFlagRolloutPercentageCommand $command): void
    {
        $state = $this->featureFlagRepository->findByKey($command->flagKey);

        if ($state !== null) {
            $this->featureFlagRepository->update($command->flagKey, ['rollout_percentage' => $command->percentage]);
        } else {
            $this->featureFlagRepository->create(['key' => $command->flagKey, 'rollout_percentage' => $command->percentage]);
        }
    }
}
