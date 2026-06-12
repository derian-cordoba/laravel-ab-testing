<?php

declare(strict_types=1);

namespace ABTests\Application\Commands;

/**
 * Creates a new feature flag state record.
 */
final readonly class CreateFeatureFlagCommand
{
    public function __construct(
        public string $key,
        public bool $isEnabled = false,
        public int $rolloutPercentage = 100,
        public string $actorIdentifier = 'dashboard',
        public string $actorType = 'user',
    ) {
        //
    }
}
