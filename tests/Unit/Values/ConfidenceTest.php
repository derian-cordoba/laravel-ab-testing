<?php

declare(strict_types=1);

namespace ABTests\Tests\Unit\Values;

use ABTests\Values\Confidence;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ConfidenceTest extends TestCase
{
    #[Test]
    public function significance_threshold_is_complement_of_level(): void
    {
        $confidence = new Confidence(0.95);

        self::assertEqualsWithDelta(0.05, $confidence->significanceThreshold(), 1e-10);
    }

    #[Test]
    public function stores_level(): void
    {
        $confidence = new Confidence(0.99);

        self::assertSame(0.99, $confidence->level);
    }

    /** @return array<string, array{float}> */
    public static function invalidLevelProvider(): array
    {
        return [
            'zero'        => [0.0],
            'negative'    => [-0.1],
            'one'         => [1.0],
            'above one'   => [1.5],
        ];
    }

    #[Test]
    #[DataProvider('invalidLevelProvider')]
    public function rejects_out_of_range_level(float $level): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Confidence($level);
    }

    #[Test]
    public function accepts_boundary_adjacent_values(): void
    {
        $low  = new Confidence(0.001);
        $high = new Confidence(0.999);

        self::assertSame(0.001, $low->level);
        self::assertSame(0.999, $high->level);
    }
}
