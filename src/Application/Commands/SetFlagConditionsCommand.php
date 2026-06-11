<?php

declare(strict_types=1);

namespace ABTests\Application\Commands;

final readonly class SetFlagConditionsCommand
{
    /**
     * @param list<array{attribute: string, operator: string, expected: mixed}> $conditions
     */
    public function __construct(
        public string $flagKey,
        public array $conditions,
        public string $actorIdentifier,
        public string $actorType = 'user',
    ) {
        //
    }
}
