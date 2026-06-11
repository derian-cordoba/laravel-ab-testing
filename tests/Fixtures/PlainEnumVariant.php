<?php

declare(strict_types=1);

namespace ABTests\Tests\Fixtures;

use ABTests\Contracts\Variant;

enum PlainEnumVariant implements Variant
{
    case control;
    case treatment;

    public function key(): string
    {
        return $this->name;
    }

    public function weight(): int
    {
        return match ($this) {
            self::control => 50,
            self::treatment => 50,
        };
    }

    public function isControl(): bool
    {
        return $this === self::control;
    }
}
