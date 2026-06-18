<?php

declare(strict_types=1);

namespace ABTests\Application\Commands;

use ABTests\Enums\ConditionsLogic;

final readonly class SetFlagConditionsCommand
{
    /**
     * @param  list<array{attribute: string, operator: string, expected: mixed}>  $conditions
     */
    public function __construct(
        public string $flagKey,
        public array $conditions,
        public string $actorIdentifier,
        public ConditionsLogic $conditionsLogic = ConditionsLogic::all,
        public string $actorType = 'user',
    ) {
        //
    }
}
