<?php

declare(strict_types=1);

namespace ABTests\Application\Commands;

final readonly class EnableFeatureFlagCommand
{
    public function __construct(
        public string $flagKey,
        public string $actorIdentifier,
        public string $actorType = 'user',
    ) {
        //
    }
}
