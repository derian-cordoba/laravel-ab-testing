<?php

declare(strict_types=1);

namespace ABTests\Tests\Unit\Resolution\Steps;

use ABTests\Application\Resolution\Steps\CheckSegmentStep;
use ABTests\Tests\Fixtures\TestUnit;
use ABTests\Tests\Support\MakesPayload;
use ABTests\Values\Segment;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CheckSegmentStepTest extends TestCase
{
    use MakesPayload;

    #[Test]
    public function returns_true_when_unit_matches_segment(): void
    {
        $definition = $this->makeDefinition(audience: Segment::where('plan', 'pro'));
        $unit = new TestUnit(attributes: ['plan' => 'pro']);
        $payload = $this->makePayload(definition: $definition, unit: $unit);

        self::assertTrue(new CheckSegmentStep()->handle($payload));
    }

    #[Test]
    public function returns_false_when_unit_does_not_match_segment(): void
    {
        $definition = $this->makeDefinition(audience: Segment::where('plan', 'pro'));
        $unit = new TestUnit(attributes: ['plan' => 'free']);
        $payload = $this->makePayload(definition: $definition, unit: $unit);

        self::assertFalse(new CheckSegmentStep()->handle($payload));
    }

    #[Test]
    public function returns_true_for_any_segment(): void
    {
        $definition = $this->makeDefinition(audience: Segment::any());
        $payload = $this->makePayload(definition: $definition);

        self::assertTrue(new CheckSegmentStep()->handle($payload));
    }
}
