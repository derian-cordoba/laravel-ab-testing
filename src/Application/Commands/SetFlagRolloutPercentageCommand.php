<?php

declare(strict_types=1);

namespace ABTests\Application\Commands;

final readonly class SetFlagRolloutPercentageCommand
{
    public function __construct(
        public string $flagKey,
        public int $percentage,
        public string $actorIdentifier,
        public string $actorType = 'user',
    ) {
        //
    }
}
