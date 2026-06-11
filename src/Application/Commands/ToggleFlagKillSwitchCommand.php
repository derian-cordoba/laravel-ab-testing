<?php

declare(strict_types=1);

namespace ABTests\Application\Commands;

final readonly class ToggleFlagKillSwitchCommand
{
    public function __construct(
        public string $flagKey,
        public bool $isKilled,
        public string $actorIdentifier,
        public string $actorType = 'user',
    ) {
        //
    }
}
