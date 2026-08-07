<?php

declare(strict_types=1);

namespace ABTests\Contracts;

/**
 * Dispatches a command to its handler. The default implementation resolves
 * handlers synchronously from the container. Consumers may swap this binding
 * in their service provider for an async bus without touching the command or
 * handler classes.
 */
interface CommandBus
{
    public function dispatch(object $command): mixed;
}
