<?php

declare(strict_types=1);

namespace ABTests\Application\Commands;

final readonly class ToggleKillSwitchCommand
{
    public function __construct(
        public string $experimentKey,
        public bool $isKilled,
        public string $actorIdentifier,
        public string $actorType = 'user',
    ) {
        //
    }
}
