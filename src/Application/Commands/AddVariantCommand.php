<?php

declare(strict_types=1);

namespace ABTests\Application\Commands;

final readonly class AddVariantCommand
{
    public function __construct(
        public string $experimentKey,
        public string $variantKey,
        public int $weight,
        public bool $isControl,
        public string $actorIdentifier,
        public string $actorType = 'user',
    ) {
        //
    }
}
