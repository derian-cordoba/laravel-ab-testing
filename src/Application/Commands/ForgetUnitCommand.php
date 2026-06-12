<?php

declare(strict_types=1);

namespace ABTests\Application\Commands;

/**
 * GDPR right-to-erasure command. Deletes all events and assignments for the
 * identified unit across every experiment. This is irreversible.
 */
final readonly class ForgetUnitCommand
{
    public function __construct(
        public string $unitType,
        public string $unitKey,
        public string $actorIdentifier,
        public string $actorType = 'user',
    ) {
        //
    }
}
