<?php

declare(strict_types=1);

namespace ABTests\Tests\Unit\Values;

use ABTests\Enums\Operator;
use ABTests\Tests\Fixtures\TestUnit;
use ABTests\Values\Segment;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SegmentTest extends TestCase
{
    #[Test]
    public function any_matches_all_units(): void
    {
        $unit = new TestUnit(attributes: []);
        self::assertTrue(Segment::any()->matches($unit));
    }

    #[Test]
    public function where_matches_matching_attribute(): void
    {
        $unit = new TestUnit(attributes: ['plan' => 'pro']);
        self::assertTrue(Segment::where('plan', 'pro')->matches($unit));
    }

    #[Test]
    public function where_does_not_match_wrong_value(): void
    {
        $unit = new TestUnit(attributes: ['plan' => 'free']);
        self::assertFalse(Segment::where('plan', 'pro')->matches($unit));
    }

    #[Test]
    public function and_adds_additional_criterion(): void
    {
        $segment = Segment::where('plan', 'pro')->and('country', 'US');

        self::assertTrue($segment->matches(new TestUnit(attributes: ['plan' => 'pro', 'country' => 'US'])));
        self::assertFalse($segment->matches(new TestUnit(attributes: ['plan' => 'pro', 'country' => 'GB'])));
        self::assertFalse($segment->matches(new TestUnit(attributes: ['plan' => 'free', 'country' => 'US'])));
    }

    #[Test]
    public function and_is_non_mutating(): void
    {
        $base = Segment::where('plan', 'pro');
        $extended = $base->and('country', 'US');

        // Original segment unchanged
        self::assertTrue($base->matches(new TestUnit(attributes: ['plan' => 'pro'])));
        // Extended requires both
        self::assertFalse($extended->matches(new TestUnit(attributes: ['plan' => 'pro'])));
    }

    #[Test]
    public function where_with_in_operator(): void
    {
        $segment = Segment::where('country', ['US', 'CA'], Operator::in);

        self::assertTrue($segment->matches(new TestUnit(attributes: ['country' => 'US'])));
        self::assertTrue($segment->matches(new TestUnit(attributes: ['country' => 'CA'])));
        self::assertFalse($segment->matches(new TestUnit(attributes: ['country' => 'GB'])));
    }
}
