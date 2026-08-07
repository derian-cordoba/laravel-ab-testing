<?php

declare(strict_types=1);

namespace ABTests\Infrastructure\Events;

use ABTests\Contracts\DomainEventDispatcher;
use Illuminate\Support\Facades\Event;

final readonly class LaravelDomainEventDispatcher implements DomainEventDispatcher
{
    public function dispatch(object $event): void
    {
        Event::dispatch($event);
    }

    public function dispatchAll(array $events): void
    {
        foreach ($events as $event) {
            Event::dispatch($event);
        }
    }
}
