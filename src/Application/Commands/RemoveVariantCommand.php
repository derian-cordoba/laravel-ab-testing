<?php

declare(strict_types=1);

namespace ABTests\Application\Commands;

final readonly class RemoveVariantCommand
{
    public function __construct(
        public string $experimentKey,
        public int $variantId,
        public string $actorIdentifier,
        public string $actorType = 'user',
    ) {
        //
    }
}
