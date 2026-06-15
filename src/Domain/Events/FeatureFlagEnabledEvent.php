<?php

declare(strict_types=1);

namespace ABTests\Domain\Events;

final readonly class FeatureFlagEnabledEvent
{
    public function __construct(
        public string $flagKey,
        public string $actorIdentifier,
        public string $actorType,
    ) {
        //
    }
}
