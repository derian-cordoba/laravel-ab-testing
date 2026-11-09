<?php

declare(strict_types=1);

namespace ABTests\Application\Handlers;

use ABTests\Application\Commands\SetFlagConditionsCommand;
use ABTests\Contracts\FeatureFlagRepository;
use ABTests\Domain\FeatureFlag\FeatureFlagAggregate;

final readonly class SetFlagConditionsCommandHandler
{
    public function __construct(
        private FeatureFlagRepository $featureFlagRepository,
    ) {}

    public function handle(SetFlagConditionsCommand $command): void
    {
        $record = $this->featureFlagRepository->findByKey($command->flagKey)
            ?? $this->featureFlagRepository->create(['key' => $command->flagKey]);

        $aggregate = FeatureFlagAggregate::reconstitute($record);
        $aggregate->setConditions($command->conditions ?: null, $command->conditionsLogic);

        $this->featureFlagRepository->update($command->flagKey, $aggregate->pendingChanges());
    }
}
