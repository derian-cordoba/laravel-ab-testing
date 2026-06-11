<?php

/**
 * PHPStan stubs — generic Container::make().
 *
 * PHPStan without Larastan types Container::make() as returning mixed.
 * This stub overrides the signature to return T when a class-string<T> is
 * passed, which lets us avoid inline @var annotations in the service provider.
 */

namespace Illuminate\Contracts\Container;

interface Container
{
    /**
     * @template T of object
     * @param class-string<T>|string $abstract
     * @param array<mixed> $parameters
     * @return ($abstract is class-string<T> ? T : mixed)
     */
    public function make($abstract, array $parameters = []): mixed;
}
