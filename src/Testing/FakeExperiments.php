<?php

declare(strict_types=1);

namespace ABTests\Testing;

use ABTests\Attributes\AsMetric;
use ABTests\Contracts\Bucketable;
use ABTests\Contracts\BucketingStrategy;
use ABTests\Contracts\Variant;
use ABTests\Experiment;
use ABTests\Experiments;
use ABTests\Infrastructure\InMemoryAssignmentRepository;
use ABTests\Infrastructure\NullFeatureFlagRepository;
use ABTests\Metric;
use ABTests\Application\Registry\ExperimentRegistry;
use ABTests\Application\Registry\FeatureFlagRegistry;
use ABTests\Infrastructure\Bucketing\Sha256BucketingStrategy;
use ABTests\Values\RecordedEvent;
use PHPUnit\Framework\Assert;
use ReflectionClass;
use Throwable;

/**
 * Testing companion for the Experiments facade.
 *
 * Call FakeExperiments::boot() at the start of a test (or in setUp()) to swap
 * the real singleton with a fake one backed by in-memory stores. The fake
 * records every exposure and conversion event so you can assert on them, and
 * lets you force a specific variant for any experiment so tests run
 * deterministically without touching the bucketing algorithm.
 *
 * Example usage:
 *
 *   $fake = FakeExperiments::boot();
 *   $fake->forceVariant(CheckoutButtonColor::class, ButtonColor::Green);
 *
 *   // ... exercise code that calls Experiments::for($user)->variant(...)
 *
 *   $fake->assertExposed(CheckoutButtonColor::class, $user);
 *
 * If you need to register experiments that are not yet wired into the Laravel
 * container (e.g. in unit tests), pass a pre-populated ExperimentRegistry:
 *
 *   $registry = new ExperimentRegistry();
 *   $registry->register($reader->readExperiment(CheckoutButtonColor::class), CheckoutButtonColor::class);
 *   $fake = FakeExperiments::bootWithRegistries($registry, new FeatureFlagRegistry());
 */
final readonly class FakeExperiments
{
    private FakeVariantResolver $variantResolver;
    private RecordingEventSink $eventSink;
    private ExperimentRegistry $registry;

    private function __construct(
        ExperimentRegistry $registry,
        FeatureFlagRegistry $flagRegistry,
        BucketingStrategy $bucketingStrategy,
    ) {
        $this->registry    = $registry;
        $this->eventSink   = new RecordingEventSink();

        // The same repository instance is shared between the resolver (which
        // writes assignments when a forced variant is returned) and the
        // Experiments singleton (which reads assignments during track()).
        $assignmentRepository  = new InMemoryAssignmentRepository();
        $this->variantResolver = new FakeVariantResolver($assignmentRepository);

        Experiments::setInstance(new Experiments(
            registry: $registry,
            flagRegistry: $flagRegistry,
            resolver: $this->variantResolver,
            eventSink: $this->eventSink,
            assignmentRepository: $assignmentRepository,
            bucketingStrategy: $bucketingStrategy,
            featureFlagRepository: new NullFeatureFlagRepository(),
        ));
    }

    // -------------------------------------------------------------------------
    // Factory methods
    // -------------------------------------------------------------------------

    /**
     * Boot the fake using the currently bound registries and bucketing strategy
     * from the Laravel container. Use this in feature/integration tests where the
     * service provider is already registered.
     */
    public static function boot(): self
    {
        try {
            $registry     = app(ExperimentRegistry::class);
            $flagRegistry = app(FeatureFlagRegistry::class);
            $strategy     = app(BucketingStrategy::class);
        } catch (Throwable) {
            $registry     = new ExperimentRegistry();
            $flagRegistry = new FeatureFlagRegistry();
            $strategy     = new Sha256BucketingStrategy();
        }

        return new self($registry, $flagRegistry, $strategy);
    }

    /**
     * Boot the fake with explicitly supplied registries. Use this in unit tests
     * that do not boot the Laravel application.
     */
    public static function bootWithRegistries(
        ExperimentRegistry $registry,
        FeatureFlagRegistry $flagRegistry,
        ?BucketingStrategy $bucketingStrategy = null,
    ): self {
        return new self($registry, $flagRegistry, $bucketingStrategy ?? new Sha256BucketingStrategy());
    }

    // -------------------------------------------------------------------------
    // Forcing variants
    // -------------------------------------------------------------------------

    /**
     * Force every unit that resolves the given experiment to receive a specific
     * variant. Chainable.
     *
     * @param class-string<Experiment> $experimentClass
     */
    public function forceVariant(string $experimentClass, Variant $variant): static
    {
        $definition = $this->registry->findByClass($experimentClass);
        $this->variantResolver->force($definition->key, $variant);

        return $this;
    }

    /**
     * Remove a previously forced variant so the experiment falls back to
     * returning null (unit not assigned). Chainable.
     *
     * @param class-string<Experiment> $experimentClass
     */
    public function removeForced(string $experimentClass): static
    {
        $definition = $this->registry->findByClass($experimentClass);
        $this->variantResolver->remove($definition->key);

        return $this;
    }

    /**
     * Clear all forced variants and recorded events, returning the fake to its
     * initial state. Useful between test cases that share a setUp().
     */
    public function reset(): static
    {
        $this->variantResolver->reset();
        $this->eventSink->reset();

        return $this;
    }

    // -------------------------------------------------------------------------
    // Assertions — exposures
    // -------------------------------------------------------------------------

    /**
     * Assert that the given unit was exposed to the given experiment (i.e.
     * variant() was called and returned a non-null variant).
     *
     * @param class-string<Experiment> $experimentClass
     */
    public function assertExposed(string $experimentClass, Bucketable $unit): void
    {
        $definition = $this->registry->findByClass($experimentClass);
        $exposures  = $this->eventSink->exposuresFor($definition->key, $unit->bucketingKey());

        Assert::assertNotEmpty(
            $exposures,
            "Expected an exposure event for [$experimentClass] / unit [{$unit->bucketingKey()}], but none was recorded.",
        );
    }

    /**
     * Assert that the given unit was NOT exposed to the given experiment.
     *
     * @param class-string<Experiment> $experimentClass
     */
    public function assertNotExposed(string $experimentClass, Bucketable $unit): void
    {
        $definition = $this->registry->findByClass($experimentClass);
        $exposures  = $this->eventSink->exposuresFor($definition->key, $unit->bucketingKey());

        Assert::assertEmpty(
            $exposures,
            "Expected no exposure event for [$experimentClass] / unit [{$unit->bucketingKey()}], but " . count($exposures) . ' was/were recorded.',
        );
    }

    /**
     * Assert that the given unit was exposed to a specific variant.
     *
     * @param class-string<Experiment> $experimentClass
     */
    public function assertExposedToVariant(string $experimentClass, Bucketable $unit, Variant $expectedVariant): void
    {
        $definition = $this->registry->findByClass($experimentClass);
        $exposures  = $this->eventSink->exposuresFor($definition->key, $unit->bucketingKey());

        Assert::assertNotEmpty(
            $exposures,
            "Expected an exposure event for [$experimentClass] / unit [{$unit->bucketingKey()}], but none was recorded.",
        );

        $variantKeys = array_unique(array_map(
            static fn ($e): string => $e->variantKey,
            $exposures,
        ));

        Assert::assertContains(
            $expectedVariant->key(),
            $variantKeys,
            "Expected unit [{$unit->bucketingKey()}] to be exposed to variant [{$expectedVariant->key()}] "
            . 'in [' . $experimentClass . '], but got: [' . implode(', ', $variantKeys) . '].',
        );
    }

    // -------------------------------------------------------------------------
    // Assertions — conversions / metric events
    // -------------------------------------------------------------------------

    /**
     * Assert that the given unit triggered a conversion or metric event for the
     * given metric. Accepts either a metric class-string or a plain key.
     *
     * @param class-string<Metric>|string $metricClassOrKey
     */
    public function assertConverted(string $metricClassOrKey, Bucketable $unit): void
    {
        $metricKey   = $this->resolveMetricKey($metricClassOrKey);
        $conversions = $this->eventSink->conversionsFor($metricKey, $unit->bucketingKey());

        Assert::assertNotEmpty(
            $conversions,
            "Expected a conversion event for metric [$metricKey] / unit [{$unit->bucketingKey()}], but none was recorded.",
        );
    }

    /**
     * Assert that the given unit did NOT trigger a conversion event for the metric.
     *
     * @param class-string<Metric>|string $metricClassOrKey
     */
    public function assertNotConverted(string $metricClassOrKey, Bucketable $unit): void
    {
        $metricKey   = $this->resolveMetricKey($metricClassOrKey);
        $conversions = $this->eventSink->conversionsFor($metricKey, $unit->bucketingKey());

        Assert::assertEmpty(
            $conversions,
            "Expected no conversion event for metric [$metricKey] / unit [{$unit->bucketingKey()}], but " . count($conversions) . ' was/were recorded.',
        );
    }

    // -------------------------------------------------------------------------
    // Introspection
    // -------------------------------------------------------------------------

    /**
     * Return all events recorded since boot (or the last reset()).
     *
     * @return list<RecordedEvent>
     */
    public function recordedEvents(): array
    {
        return $this->eventSink->allEvents();
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function resolveMetricKey(string $metricClassOrKey): string
    {
        if (! class_exists($metricClassOrKey)) {
            return $metricClassOrKey;
        }

        try {
            $reflector = new ReflectionClass($metricClassOrKey);
            $attrs     = $reflector->getAttributes(AsMetric::class);

            if ($attrs !== []) {
                return $attrs[0]->newInstance()->key;
            }
        } catch (Throwable) {
            // Not a metric class — treat as a raw key.
        }

        return $metricClassOrKey;
    }
}
