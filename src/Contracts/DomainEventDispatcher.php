<?php

declare(strict_types=1);

namespace ABTests\Contracts;

interface DomainEventDispatcher
{
    public function dispatch(object $event): void;

    /** @param list<object> $events */
    public function dispatchAll(array $events): void;
}
