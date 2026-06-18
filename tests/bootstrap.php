<?php

declare(strict_types=1);

use Illuminate\Container\Container;

require __DIR__.'/../vendor/autoload.php';

/**
 * config() is provided by illuminate/foundation in a full Laravel app.
 * The test suite uses only illuminate component packages, so we define a
 * lightweight stub that delegates to the bound ConfigRepository when the
 * container is set up, and falls back to $default otherwise.
 */
if (! function_exists('config')) {
    function config(?string $key = null, mixed $default = null): mixed
    {
        $container = Container::getInstance();

        if ($container === null || ! $container->bound('config')) {
            return $default;
        }

        $repository = $container->make('config');

        if ($key === null) {
            return $repository;
        }

        return $repository->get($key, $default);
    }
}
