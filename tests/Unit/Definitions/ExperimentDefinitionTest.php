<?php

declare(strict_types=1);

namespace ABTests\Tests\Unit\Definitions;

use ABTests\Definitions\MetricBinding;
use ABTests\Enums\MetricRole;
use ABTests\Tests\Support\MakesDefinition;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ExperimentDefinitionTest extends TestCase
{
    use MakesDefinition;

    #[Test]
    public function primary_metric_returns_the_primary_binding(): void
    {
        $definition = $this->makeDefinition(metrics: [
            new MetricBinding('secondary-metric', MetricRole::secondary),
            new MetricBinding('primary-metric', MetricRole::primary),
        ]);

        self::assertSame('primary-metric', $definition->primaryMetric()->metric);
    }

    #[Test]
    public function primary_metric_throws_when_none_defined(): void
    {
        $definition = $this->makeDefinition(metrics: [
            new MetricBinding('guardrail', MetricRole::guardrail),
        ]);

        $this->expectException(LogicException::class);
        $definition->primaryMetric();
    }

    #[Test]
    public function guardrails_returns_only_guardrail_bindings(): void
    {
        $definition = $this->makeDefinition(metrics: [
            new MetricBinding('primary', MetricRole::primary),
            new MetricBinding('guardrail-a', MetricRole::guardrail, 0.05),
            new MetricBinding('secondary', MetricRole::secondary),
            new MetricBinding('guardrail-b', MetricRole::guardrail, 0.02),
        ]);

        $guardrails = $definition->guardrails();

        self::assertCount(2, $guardrails);
        self::assertSame('guardrail-a', $guardrails[0]->metric);
        self::assertSame('guardrail-b', $guardrails[1]->metric);
    }

    #[Test]
    public function guardrails_returns_empty_array_when_none(): void
    {
        $definition = $this->makeDefinition(metrics: [
            new MetricBinding('primary', MetricRole::primary),
        ]);

        self::assertSame([], $definition->guardrails());
    }

    #[Test]
    public function stores_key_and_layer(): void
    {
        $definition = $this->makeDefinition(key: 'my-exp', layer: 'checkout');

        self::assertSame('my-exp', $definition->key);
        self::assertSame('checkout', $definition->layer);
    }
}
