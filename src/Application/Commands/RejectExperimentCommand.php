<?php

declare(strict_types=1);

namespace ABTests\Application\Commands;

final readonly class RejectExperimentCommand
{
    public function __construct(
        public string $experimentKey,
        public string $actorIdentifier,
        public string $actorType = 'user',
        public ?string $notes = null,
    ) {
        //
    }
}
