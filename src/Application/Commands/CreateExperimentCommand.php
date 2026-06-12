<?php

declare(strict_types=1);

namespace ABTests\Application\Commands;

/**
 * Creates a new runtime-defined experiment in draft status.
 * The experiment starts with no variants; use AddVariantCommand to populate them.
 */
final readonly class CreateExperimentCommand
{
    public function __construct(
        public string $key,
        public ?string $name,
        public ?string $layer,
        public int $trafficPercentage = 0,
        public string $actorIdentifier = 'dashboard',
        public string $actorType = 'user',
    ) {
        //
    }
}
