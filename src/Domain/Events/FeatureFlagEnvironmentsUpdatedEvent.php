<?php

declare(strict_types=1);

namespace ABTests\Domain\Events;

final readonly class FeatureFlagEnvironmentsUpdatedEvent
{
    /**
     * @param list<string>|null $allowedEnvironments null = all environments allowed.
     */
    public function __construct(
        public string $flagKey,
        public ?array $allowedEnvironments,
        public string $actorIdentifier,
        public string $actorType = 'user',
    ) {
        //
    }
}
