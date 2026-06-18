<?php

declare(strict_types=1);

namespace ABTests\Tests\Support;

use ABTests\Definitions\ExperimentDefinition;
use ABTests\Definitions\MetricBinding;
use ABTests\Enums\MetricRole;
use ABTests\Tests\Fixtures\TestVariant;
use ABTests\Values\Allocation;
use ABTests\Values\AnalysisConfiguration;
use ABTests\Values\GenericVariant;
use ABTests\Values\Segment;

/**
 * Factory helpers for constructing ExperimentDefinition instances in tests
 * without repeating boilerplate.
 */
trait MakesDefinition
{
    /**
     * Build a minimal two-variant definition with sensible defaults.
     *
     * @param  array<MetricBinding>  $metrics
     */
    protected function makeDefinition(
        string $key = 'test-experiment',
        string $unitType = 'test-user',
        ?Segment $audience = null,
        ?string $layer = null,
        array $metrics = [],
    ): ExperimentDefinition {
        return new ExperimentDefinition(
            key: $key,
            unitType: $unitType,
            allocation: Allocation::fromVariants(TestVariant::cases()),
            analysis: AnalysisConfiguration::default(),
            audience: $audience ?? Segment::any(),
            metrics: $metrics,
            layer: $layer,
        );
    }

    /**
     * Build a definition with generic (non-enum) variants.
     *
     * @param  array<array{key: string, weight: int, control: bool}>  $variantSpecs
     */
    protected function makeGenericDefinition(
        string $key = 'generic-experiment',
        array $variantSpecs = [
            ['key' => 'control', 'weight' => 50, 'control' => true],
            ['key' => 'treatment', 'weight' => 50, 'control' => false],
        ],
    ): ExperimentDefinition {
        $variants = array_map(
            static fn (array $spec): GenericVariant => new GenericVariant(
                key: $spec['key'],
                weight: $spec['weight'],
                isControl: $spec['control'],
            ),
            $variantSpecs,
        );

        return new ExperimentDefinition(
            key: $key,
            unitType: 'user',
            allocation: Allocation::fromVariants($variants),
            analysis: AnalysisConfiguration::default(),
            audience: Segment::any(),
            metrics: [],
        );
    }

    /** Build a MetricBinding for the primary role. */
    protected function primaryBinding(string $metric = 'test-metric'): MetricBinding
    {
        return new MetricBinding($metric, MetricRole::primary);
    }

    /** Build a MetricBinding for the guardrail role. */
    protected function guardrailBinding(
        string $metric = 'guardrail-metric',
        float $maximumRegression = 0.05,
    ): MetricBinding {
        return new MetricBinding($metric, MetricRole::guardrail, $maximumRegression);
    }
}
