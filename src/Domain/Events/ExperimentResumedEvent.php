<?php

declare(strict_types=1);

namespace ABTests\Domain\Events;

final readonly class ExperimentResumedEvent
{
    public function __construct(
        public string $experimentKey,
        public string $actorIdentifier,
        public string $actorType,
    ) {
        //
    }
}
