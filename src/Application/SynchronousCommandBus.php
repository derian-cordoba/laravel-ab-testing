<?php

declare(strict_types=1);

namespace ABTests\Application;

use ABTests\Contracts\CommandBus;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Container\Container;

/**
 * Resolves each command's handler synchronously from the container by
 * appending "Handler" to the command's fully-qualified class name, then
 * calls handle($command) on it.
 *
 * Example: StartExperimentCommand → StartExperimentCommandHandler::handle()
 */
final readonly class SynchronousCommandBus implements CommandBus
{
    public function __construct(private Container $container)
    {
        //
    }

    /**
     * @throws BindingResolutionException
     */
    public function dispatch(object $command): mixed
    {
        // Derive the handler class from the command class:
        //   ABTests\Application\Commands\StartExperimentCommand
        //   → ABTests\Application\Handlers\StartExperimentCommandHandler
        //
        // We do the namespace swap first, then simply append "Handler" — using a
        // single str_replace on 'Command' → 'CommandHandler' was incorrect because
        // it would also corrupt the already-replaced 'Handlers' segment.
        $handlerClass = str_replace('\\Commands\\', '\\Handlers\\', get_class($command)).'Handler';

        $handler = $this->container->make($handlerClass);

        return $handler->handle($command);
    }
}
