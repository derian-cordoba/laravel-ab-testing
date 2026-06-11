<?php

declare(strict_types=1);

namespace ABTests\Tests\Unit\Concerns;

use ABTests\Tests\Fixtures\TestVariant;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IsVariantTest extends TestCase
{
    #[Test]
    public function key_returns_backing_value(): void
    {
        self::assertSame('control', TestVariant::control->key());
        self::assertSame('treatment', TestVariant::treatment->key());
    }

    #[Test]
    public function weight_reads_attribute(): void
    {
        self::assertSame(50, TestVariant::control->weight());
        self::assertSame(50, TestVariant::treatment->weight());
    }

    #[Test]
    public function is_control_true_for_control_case(): void
    {
        self::assertTrue(TestVariant::control->isControl());
    }

    #[Test]
    public function is_control_false_for_treatment_case(): void
    {
        self::assertFalse(TestVariant::treatment->isControl());
    }
}
