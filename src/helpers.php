<?php

declare(strict_types=1);

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
