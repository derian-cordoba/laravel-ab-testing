<?php

declare(strict_types=1);

namespace ABTests\Tests\Unit\Values;

use ABTests\Enums\Environment;
use ABTests\Tests\Fixtures\TestUnit;
use ABTests\Values\Context;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ContextTest extends TestCase
{
    private function makeContext(float $position = 0.3, array $attributes = []): Context
    {
        return new Context(
            unit: new TestUnit(attributes: $attributes),
            environment: Environment::production,
            position: $position,
        );
    }

    #[Test]
    public function attribute_returns_unit_attribute(): void
    {
        $ctx = $this->makeContext(attributes: ['plan' => 'pro']);
        self::assertSame('pro', $ctx->attribute('plan'));
    }

    #[Test]
    public function attribute_returns_default_when_missing(): void
    {
        $ctx = $this->makeContext();
        self::assertNull($ctx->attribute('nonexistent'));
        self::assertSame('fallback', $ctx->attribute('nonexistent', 'fallback'));
    }

    #[Test]
    public function in_rollout_true_when_position_below_percentage(): void
    {
        $ctx = $this->makeContext(position: 0.2);
        self::assertTrue($ctx->inRollout(30));
    }

    #[Test]
    public function in_rollout_false_when_position_above_percentage(): void
    {
        $ctx = $this->makeContext(position: 0.6);
        self::assertFalse($ctx->inRollout(50));
    }

    #[Test]
    public function in_rollout_false_at_exact_boundary(): void
    {
        $ctx = $this->makeContext(position: 0.5);
        self::assertFalse($ctx->inRollout(50));
    }
}
