<?php

declare(strict_types=1);

namespace ABTests\Domain\Events;

final readonly class ExperimentStartedEvent
{
    public function __construct(
        public string $experimentKey,
        public string $actorIdentifier,
        public string $actorType,
        public int $trafficPercentage,
    ) {
        //
    }
}
