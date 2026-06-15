<?php

declare(strict_types=1);

namespace ABTests\Domain\Events;

final readonly class KillSwitchActivatedEvent
{
    public function __construct(
        /** Set when the kill switch is on an experiment; null for feature flags. */
        public ?string $experimentKey,
        /** Set when the kill switch is on a feature flag; null for experiments. */
        public ?string $flagKey,
        /** True when the kill switch was activated; false when it was deactivated. */
        public bool $activated,
        public string $actorIdentifier,
        public string $actorType,
    ) {
        //
    }
}
