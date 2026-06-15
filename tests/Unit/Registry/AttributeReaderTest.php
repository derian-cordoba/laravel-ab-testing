<?php

declare(strict_types=1);

namespace ABTests\Tests\Unit\Registry;

use ABTests\Enums\MetricRole;
use ABTests\Enums\StatisticalEngine;
use ABTests\Experiment;
use ABTests\Application\Registry\AttributeReader;
use ABTests\Tests\Fixtures\BackedEnumExperiment;
use ABTests\Tests\Fixtures\ExperimentWithMissingUnitAttribute;
use ABTests\Tests\Fixtures\ExperimentWithPlainEnumVariants;
use ABTests\Tests\Fixtures\PlainMetricKeyExperiment;
use ABTests\Tests\Fixtures\TestExperiment;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AttributeReaderTest extends TestCase
{
    private AttributeReader $reader;

    protected function setUp(): void
    {
        $this->reader = new AttributeReader();
    }

    #[Test]
    public function reads_experiment_key(): void
    {
        $definition = $this->reader->readExperiment(TestExperiment::class);
        self::assertSame('test-experiment', $definition->key);
    }

    #[Test]
    public function reads_experiment_name_and_layer(): void
    {
        $definition = $this->reader->readExperiment(TestExperiment::class);
        self::assertSame('Test Experiment', $definition->name);
        self::assertSame('test-layer', $definition->layer);
    }

    #[Test]
    public function reads_unit_type_from_as_unit_attribute(): void
    {
        $definition = $this->reader->readExperiment(TestExperiment::class);
        self::assertSame('test-user', $definition->unitType);
    }

    #[Test]
    public function builds_allocation_from_variants_enum(): void
    {
        $definition = $this->reader->readExperiment(TestExperiment::class);
        self::assertCount(2, $definition->allocation->variants);
        self::assertSame('control', $definition->allocation->control()->key());
    }

    #[Test]
    public function reads_metric_bindings(): void
    {
        $definition = $this->reader->readExperiment(TestExperiment::class);

        $primaryMetric = $definition->primaryMetric();
        self::assertSame('test-conversion', $primaryMetric->metric);
        self::assertSame(MetricRole::primary, $primaryMetric->role);
    }

    #[Test]
    public function reads_guardrail_binding(): void
    {
        $definition = $this->reader->readExperiment(TestExperiment::class);

        $guardrails = $definition->guardrails();
        self::assertCount(1, $guardrails);
        self::assertEqualsWithDelta(0.01, $guardrails[0]->maximumRegression, 1e-10);
    }

    #[Test]
    public function reads_analysis_configuration(): void
    {
        $definition = $this->reader->readExperiment(TestExperiment::class);
        self::assertSame(StatisticalEngine::both, $definition->analysis->engine);
        self::assertEqualsWithDelta(0.95, $definition->analysis->confidence->level, 1e-10);
        self::assertFalse($definition->analysis->sequential);
    }

    #[Test]
    public function reads_audience_from_overridden_method(): void
    {
        $definition = $this->reader->readExperiment(TestExperiment::class);
        // Audience requires plan=pro
        self::assertCount(1, $definition->audience->criteria);
    }

    #[Test]
    public function reads_backed_enum_keys_from_experiment_and_unit_attributes(): void
    {
        $definition = $this->reader->readExperiment(BackedEnumExperiment::class);

        self::assertSame('checkout-button-color', $definition->key);
        self::assertSame('tenant', $definition->unitType);
    }

    #[Test]
    public function preserves_plain_metric_keys_when_reading_attribute_bindings(): void
    {
        $definition = $this->reader->readExperiment(PlainMetricKeyExperiment::class);

        self::assertSame('signup-completed', $definition->primaryMetric()->metric);
        self::assertSame('payment-failure-rate', $definition->guardrails()[0]->metric);
    }

    #[Test]
    public function throws_when_unit_class_is_missing_as_unit_attribute(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('missing the #[AsUnit] attribute');

        $this->reader->readExperiment(ExperimentWithMissingUnitAttribute::class);
    }

    #[Test]
    public function throws_when_variants_enum_is_not_backed(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('must be a backed enum');

        $this->reader->readExperiment(ExperimentWithPlainEnumVariants::class);
    }

    #[Test]
    public function throws_when_as_experiment_attribute_missing(): void
    {
        $bareExperiment = new class extends Experiment {};

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('missing the required');
        $this->reader->readExperiment($bareExperiment::class);
    }
}
