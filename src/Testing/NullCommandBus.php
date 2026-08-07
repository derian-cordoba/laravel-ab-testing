<?php

declare(strict_types=1);

namespace ABTests\Testing;

use ABTests\Contracts\CommandBus;

/**
 * No-op command bus for unit tests that do not need forget() or recordCovariate().
 */
final readonly class NullCommandBus implements CommandBus
{
    public function dispatch(object $command): mixed
    {
        return null;
    }
}
