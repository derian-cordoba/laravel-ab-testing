<?php

declare(strict_types=1);

namespace ABTests\Application\Commands;

/**
 * Updates the editable metadata fields of an existing experiment.
 * Structural fields (layer) are locked once the experiment leaves draft/scheduled.
 */
final readonly class UpdateExperimentCommand
{
    public function __construct(
        public string $experimentKey,
        public ?string $name,
        public ?string $layer,
        public ?int $targetSampleSize,
        public string $actorIdentifier = 'dashboard',
        public string $actorType = 'user',
    ) {
        //
    }
}
