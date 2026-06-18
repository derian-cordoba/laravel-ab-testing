<?php

declare(strict_types=1);

namespace ABTests\Application\Handlers;

use ABTests\Application\Commands\SetFlagConditionsCommand;
use ABTests\Contracts\FeatureFlagRepository;

final readonly class SetFlagConditionsCommandHandler
{
    public function __construct(
        private FeatureFlagRepository $featureFlagRepository,
    ) {}

    public function handle(SetFlagConditionsCommand $command): void
    {
        $state = $this->featureFlagRepository->findByKey($command->flagKey);

        $attributes = [
            'conditions' => $command->conditions ?: null,
            'conditions_logic' => $command->conditionsLogic,
        ];

        if ($state !== null) {
            $this->featureFlagRepository->update($command->flagKey, $attributes);
        } else {
            $this->featureFlagRepository->create(array_merge(['key' => $command->flagKey], $attributes));
        }
    }
}
