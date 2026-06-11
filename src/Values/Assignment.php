<?php

declare(strict_types=1);

namespace ABTests\Values;

use DateTimeImmutable;

/**
 * A persisted, sticky variant assignment for one unit on one experiment.
 * Written once (on first resolution) and re-read on every subsequent
 * resolution to guarantee the same variant is always returned.
 */
final readonly class Assignment
{
    public function __construct(
        public string $experimentKey,
        public string $unitType,
        public string $unitKey,
        public string $variantKey,
        public ?string $layer,
        public DateTimeImmutable $assignedAt,
    ) {
    }
}
