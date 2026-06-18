<?php

declare(strict_types=1);

/**
 * PHPStan stubs for Laravel global helpers and generic container signatures.
 * These exist only so PHPStan can resolve types without a running application.
 * They are never executed in production.
 */

// ---------------------------------------------------------------------------
// enum_value() — illuminate/support >= 11
// ---------------------------------------------------------------------------

if (! function_exists('enum_value')) {
    /**
     * Return the scalar value of a backed enum, the name of a pure enum, or
     * the input itself when it is already a string / other scalar.
     *
     * @param  BackedEnum|UnitEnum|string  $value
     */
    function enum_value(mixed $value, mixed $default = null): mixed
    {
        return $value instanceof BackedEnum
            ? $value->value
            : ($value instanceof UnitEnum ? $value->name : $value);
    }
}
