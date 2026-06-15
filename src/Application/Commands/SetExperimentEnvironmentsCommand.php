<?php

declare(strict_types=1);

namespace ABTests\Application\Commands;

final readonly class SetExperimentEnvironmentsCommand
{
    /**
     * @param list<string>|null $allowedEnvironments null removes the restriction (all environments).
     */
    public function __construct(
        public string $experimentKey,
        public ?array $allowedEnvironments,
        public string $actorIdentifier,
        public string $actorType = 'user',
    ) {
        //
    }
}
