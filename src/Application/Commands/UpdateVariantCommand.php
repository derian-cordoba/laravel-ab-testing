<?php

declare(strict_types=1);

namespace ABTests\Application\Commands;

final readonly class UpdateVariantCommand
{
    public function __construct(
        public string $experimentKey,
        public int $variantId,
        public string $variantKey,
        public int $weight,
        public bool $isControl,
        public string $actorIdentifier,
        public string $actorType = 'user',
    ) {
        //
    }
}
