<?php

declare(strict_types=1);

namespace ABTests\Application\Handlers;

use ABTests\Application\Commands\SetFlagRolloutPercentageCommand;
use ABTests\Contracts\FeatureFlagRepository;
use ABTests\Domain\FeatureFlag\FeatureFlagAggregate;

final readonly class SetFlagRolloutPercentageCommandHandler
{
    public function __construct(
        private FeatureFlagRepository $featureFlagRepository,
    ) {}

    public function handle(SetFlagRolloutPercentageCommand $command): void
    {
        $record = $this->featureFlagRepository->findByKey($command->flagKey)
            ?? $this->featureFlagRepository->create(['key' => $command->flagKey]);

        $aggregate = FeatureFlagAggregate::reconstitute($record);
        $aggregate->setRolloutPercentage($command->percentage);

        $this->featureFlagRepository->update($command->flagKey, $aggregate->pendingChanges());
    }
}
