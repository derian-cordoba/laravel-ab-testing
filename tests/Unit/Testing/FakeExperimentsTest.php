<?php

declare(strict_types=1);

namespace ABTests\Tests\Unit\Testing;

use ABTests\Enums\EventType;
use ABTests\Experiments;
use ABTests\Application\Registry\AttributeReader;
use ABTests\Application\Registry\ExperimentRegistry;
use ABTests\Application\Registry\FeatureFlagRegistry;
use ABTests\Testing\FakeExperiments;
use ABTests\Tests\Fixtures\TestExperiment;
use ABTests\Tests\Fixtures\TestMetric;
use ABTests\Tests\Fixtures\TestUnitType;
use ABTests\Tests\Fixtures\TestVariant;
use PHPUnit\Framework\TestCase;

/**
 * Verifies that FakeExperiments correctly intercepts the Experiments singleton,
 * forces variants, records events, and provides accurate assertions.
 */
final class FakeExperimentsTest extends TestCase
{
    private ExperimentRegistry $registry;
    private FakeExperiments $fake;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = new ExperimentRegistry();
        $reader         = new AttributeReader();
        $definition     = $reader->readExperiment(TestExperiment::class);
        $this->registry->register($definition, TestExperiment::class);

        $this->fake = FakeExperiments::bootWithRegistries(
            $this->registry,
            new FeatureFlagRegistry(),
        );
    }

    public function test_no_forced_variant_returns_null(): void
    {
        $unit    = new TestUnitType('user-1', ['plan' => 'pro']);
        $variant = Experiments::for($unit)->variant(TestExperiment::class);

        self::assertNull($variant);
    }

    public function test_forced_variant_is_returned_for_all_units(): void
    {
        $this->fake->forceVariant(TestExperiment::class, TestVariant::treatment);

        $variantA = Experiments::for(new TestUnitType('user-1', ['plan' => 'pro']))->variant(TestExperiment::class);
        $variantB = Experiments::for(new TestUnitType('user-2', ['plan' => 'pro']))->variant(TestExperiment::class);

        self::assertSame(TestVariant::treatment, $variantA);
        self::assertSame(TestVariant::treatment, $variantB);
    }

    public function test_forced_variant_can_be_removed(): void
    {
        $this->fake->forceVariant(TestExperiment::class, TestVariant::treatment);
        $this->fake->removeForced(TestExperiment::class);

        $unit    = new TestUnitType('user-1', ['plan' => 'pro']);
        $variant = Experiments::for($unit)->variant(TestExperiment::class);

        self::assertNull($variant);
    }

    public function test_assert_exposed_passes_after_variant_is_resolved(): void
    {
        $this->fake->forceVariant(TestExperiment::class, TestVariant::treatment);

        $unit = new TestUnitType('user-1', ['plan' => 'pro']);
        Experiments::for($unit)->variant(TestExperiment::class);

        // Should not throw.
        $this->fake->assertExposed(TestExperiment::class, $unit);
    }

    public function test_assert_not_exposed_passes_when_null_variant_returned(): void
    {
        // No forced variant → resolver returns null → no exposure event recorded.
        $unit = new TestUnitType('user-1', ['plan' => 'pro']);
        Experiments::for($unit)->variant(TestExperiment::class);

        // Should not throw.
        $this->fake->assertNotExposed(TestExperiment::class, $unit);
    }

    public function test_assert_exposed_fails_when_no_exposure_recorded(): void
    {
        $unit = new TestUnitType('user-1', ['plan' => 'pro']);

        $this->expectException(\PHPUnit\Framework\AssertionFailedError::class);
        $this->fake->assertExposed(TestExperiment::class, $unit);
    }

    public function test_assert_exposed_to_variant_passes_for_correct_variant(): void
    {
        $this->fake->forceVariant(TestExperiment::class, TestVariant::treatment);

        $unit = new TestUnitType('user-1', ['plan' => 'pro']);
        Experiments::for($unit)->variant(TestExperiment::class);

        $this->fake->assertExposedToVariant(TestExperiment::class, $unit, TestVariant::treatment);
    }

    public function test_assert_exposed_to_variant_fails_for_wrong_variant(): void
    {
        $this->fake->forceVariant(TestExperiment::class, TestVariant::treatment);

        $unit = new TestUnitType('user-1', ['plan' => 'pro']);
        Experiments::for($unit)->variant(TestExperiment::class);

        $this->expectException(\PHPUnit\Framework\AssertionFailedError::class);
        $this->fake->assertExposedToVariant(TestExperiment::class, $unit, TestVariant::control);
    }

    public function test_assert_converted_passes_after_track(): void
    {
        $this->fake->forceVariant(TestExperiment::class, TestVariant::treatment);

        $unit = new TestUnitType('user-1', ['plan' => 'pro']);
        Experiments::for($unit)->variant(TestExperiment::class);
        Experiments::for($unit)->track(TestMetric::class);

        $this->fake->assertConverted(TestMetric::class, $unit);
    }

    public function test_assert_not_converted_passes_when_no_track_called(): void
    {
        $unit = new TestUnitType('user-1', ['plan' => 'pro']);

        $this->fake->assertNotConverted(TestMetric::class, $unit);
    }

    public function test_assert_converted_also_accepts_raw_metric_key(): void
    {
        $this->fake->forceVariant(TestExperiment::class, TestVariant::treatment);

        $unit = new TestUnitType('user-1', ['plan' => 'pro']);
        Experiments::for($unit)->variant(TestExperiment::class);
        Experiments::for($unit)->track('test-conversion');

        $this->fake->assertConverted('test-conversion', $unit);
    }

    public function test_reset_clears_forced_variants_and_recorded_events(): void
    {
        $this->fake->forceVariant(TestExperiment::class, TestVariant::treatment);

        $unit = new TestUnitType('user-1', ['plan' => 'pro']);
        Experiments::for($unit)->variant(TestExperiment::class);

        $this->fake->reset();

        // After reset the forced variant is gone → resolver returns null → no event.
        $variantAfterReset = Experiments::for($unit)->variant(TestExperiment::class);
        self::assertNull($variantAfterReset);

        $this->fake->assertNotExposed(TestExperiment::class, $unit);
        self::assertEmpty($this->fake->recordedEvents());
    }

    public function test_recorded_events_returns_all_events(): void
    {
        $this->fake->forceVariant(TestExperiment::class, TestVariant::treatment);

        $unit = new TestUnitType('user-1', ['plan' => 'pro']);
        Experiments::for($unit)->variant(TestExperiment::class);
        Experiments::for($unit)->track(TestMetric::class);

        $events    = $this->fake->recordedEvents();
        $exposures = array_filter($events, static fn ($e) => $e->type === EventType::exposure);
        $metrics   = array_filter($events, static fn ($e) => $e->type === EventType::metric);

        // Exactly one exposure event.
        self::assertCount(1, $exposures);

        // TestExperiment binds TestMetric as primary, secondary, AND guardrail,
        // so track() emits one metric event per binding — three in total.
        self::assertCount(3, $metrics);
        self::assertCount(4, $events);
    }

    public function test_exposures_are_scoped_to_the_specific_unit(): void
    {
        $this->fake->forceVariant(TestExperiment::class, TestVariant::treatment);

        $unitA = new TestUnitType('user-a', ['plan' => 'pro']);
        $unitB = new TestUnitType('user-b', ['plan' => 'pro']);

        Experiments::for($unitA)->variant(TestExperiment::class);

        // unitA exposed → assert passes.
        $this->fake->assertExposed(TestExperiment::class, $unitA);

        // unitB never resolved → assert not-exposed passes.
        $this->fake->assertNotExposed(TestExperiment::class, $unitB);
    }

    public function test_chained_force_and_assertions(): void
    {
        $unit = new TestUnitType('user-1', ['plan' => 'pro']);

        $this->fake
            ->forceVariant(TestExperiment::class, TestVariant::control);

        Experiments::for($unit)->variant(TestExperiment::class);

        $this->fake->assertExposedToVariant(TestExperiment::class, $unit, TestVariant::control);
    }
}
