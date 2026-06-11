<?php

declare(strict_types=1);

use Illuminate\Container\Container;
use Illuminate\Support\Facades\Facade;

if (! function_exists('app')) {
    /**
     * Resolve an abstract from the container, or return the container itself.
     * Mirrors illuminate/foundation's app() helper using only illuminate/support
     * and illuminate/container — both of which this package requires.
     */
    function app(?string $abstract = null): mixed
    {
        $instance = Facade::getFacadeApplication()
            ?? Container::getInstance();

        if ($abstract === null) {
            return $instance;
        }

        return $instance->make($abstract);
    }
}

if (! function_exists('enum_value')) {
    function enum_value(string|UnitEnum $value): string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        return $value;
    }
}
