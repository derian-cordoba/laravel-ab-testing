<?php

declare(strict_types=1);

namespace ABTests\Application\Commands;

final readonly class RampTrafficCommand
{
    public function __construct(
        public string $experimentKey,
        public int $trafficPercentage,
        public string $actorIdentifier,
        public string $actorType = 'user',
    ) {
        //
    }
}
