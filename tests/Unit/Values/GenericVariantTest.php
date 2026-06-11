<?php

declare(strict_types=1);

namespace ABTests\Tests\Unit\Values;

use ABTests\Values\GenericVariant;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GenericVariantTest extends TestCase
{
    #[Test]
    public function exposes_key(): void
    {
        $variant = new GenericVariant('green', 25);
        self::assertSame('green', $variant->key());
    }

    #[Test]
    public function exposes_weight(): void
    {
        $variant = new GenericVariant('green', 25);
        self::assertSame(25, $variant->weight());
    }

    #[Test]
    public function is_not_control_by_default(): void
    {
        $variant = new GenericVariant('green', 25);
        self::assertFalse($variant->isControl());
    }

    #[Test]
    public function can_be_marked_as_control(): void
    {
        $variant = new GenericVariant('control', 50, isControl: true);
        self::assertTrue($variant->isControl());
    }
}
