<?php

declare(strict_types=1);

namespace ABTests\Application\Commands;

final readonly class ResumeExperimentCommand
{
    public function __construct(
        public string $experimentKey,
        public string $actorIdentifier,
        public string $actorType = 'user',
    ) {
        //
    }
}
