<?php

declare(strict_types=1);

namespace ABTests;

use ABTests\Attributes\AsMetric;
use ABTests\Contracts\AssignmentRepository;
use ABTests\Contracts\Bucketable;
use ABTests\Contracts\EventSink;
use ABTests\Contracts\ResolvesVariant;
use ABTests\Contracts\Variant;
use ABTests\Enums\EventType;
use ABTests\Application\Registry\ExperimentRegistry;
use ABTests\Values\RecordedEvent;
use DateTimeImmutable;
use ReflectionClass;

/**
 * Fluent per-unit handle returned by Experiments::for($unit). Bundles the unit
 * with all the services needed to resolve variants, record exposures, and track
 * conversions/metrics. One instance per unit per request; never cached.
 */
final readonly class ExperimentResolver
{
    public function __construct(
        private Bucketable $unit,
        private ExperimentRegistry $registry,
        private ResolvesVariant $resolver,
        private EventSink $eventSink,
        private AssignmentRepository $assignmentRepository,
    ) {
        //
    }

    /**
     * Resolve the variant assigned to the unit for the given experiment and
     * record an exposure event. Returns null when the unit is not eligible
     * (wrong segment, held out, layer-excluded, or experiment not running).
     *
     * The returned value is the original enum case for code-defined experiments,
     * so an exhaustive match() works directly without string comparisons.
     *
     * @param class-string<Experiment> $experimentClass
     */
    public function variant(string $experimentClass): ?Variant
    {
        $definition = $this->registry->findByClass($experimentClass);
        $variant = $this->resolver->resolve($definition, $this->unit);

        if ($variant !== null) {
            $this->eventSink->record(new RecordedEvent(
                experimentKey: $definition->key,
                unitType: $definition->unitType,
                unitKey: $this->unit->bucketingKey(),
                variantKey: $variant->key(),
                type: EventType::exposure,
                idempotencyKey: "exposure:$definition->key:{$this->unit->bucketingKey()}",
                occurredAt: new DateTimeImmutable(),
            ));
        }

        return $variant;
    }

    /**
     * Resolve the variant assigned to the unit by experiment key string rather
     * than class name. Intended for Blade directives and runtime-defined
     * experiments that have no corresponding PHP class. Records an exposure
     * event when a variant is resolved, identical to variant().
     */
    public function variantForKey(string $experimentKey): ?Variant
    {
        $definition = $this->registry->findByKey($experimentKey);
        $variant    = $this->resolver->resolve($definition, $this->unit);

        if ($variant !== null) {
            $this->eventSink->record(new RecordedEvent(
                experimentKey: $definition->key,
                unitType: $definition->unitType,
                unitKey: $this->unit->bucketingKey(),
                variantKey: $variant->key(),
                type: EventType::exposure,
                idempotencyKey: "exposure:$definition->key:{$this->unit->bucketingKey()}",
                occurredAt: new DateTimeImmutable(),
            ));
        }

        return $variant;
    }

    /**
     * Record a conversion or continuous-metric event for every experiment in
     * which the unit currently has a live assignment and that experiment uses
     * the given metric.
     *
     * Accepts either a metric class-string (code-defined) or a raw metric key
     * (runtime-defined). The metric key is resolved from the #[AsMetric]
     * attribute when a class is passed.
     *
     * @param class-string<Metric>|string $metricClassOrKey
     */
    public function track(string $metricClassOrKey, ?float $value = null): void
    {
        $metricKey = $this->resolveMetricKey($metricClassOrKey);

        foreach ($this->registry->all() as $definition) {
            foreach ($definition->metrics as $binding) {
                if ($this->resolveMetricKey($binding->metric) !== $metricKey) {
                    continue;
                }

                $assignment = $this->assignmentRepository->findAssignment(
                    experimentKey: $definition->key,
                    unitType: $definition->unitType,
                    unitKey: $this->unit->bucketingKey(),
                );

                if ($assignment === null) {
                    continue;
                }

                $this->eventSink->record(new RecordedEvent(
                    experimentKey: $definition->key,
                    unitType: $definition->unitType,
                    unitKey: $this->unit->bucketingKey(),
                    variantKey: $assignment->variantKey,
                    type: EventType::metric,
                    idempotencyKey: $this->metricIdempotencyKey($definition->key, $metricKey),
                    occurredAt: new DateTimeImmutable(),
                    metricKey: $metricKey,
                    value: $value,
                ));
            }
        }
    }

    // -------------------------------------------------------------------------

    /**
     * Resolve a class-string to its #[AsMetric] key, or return the string as-is
     * if it is already a plain key (no class with that name exists).
     */
    private function resolveMetricKey(string $metricClassOrKey): string
    {
        if (! class_exists($metricClassOrKey)) {
            return $metricClassOrKey;
        }

        $reflector = new ReflectionClass($metricClassOrKey);
        $attrs = $reflector->getAttributes(AsMetric::class);

        if ($attrs === []) {
            return $metricClassOrKey;
        }

        /** @var AsMetric $asMetric */
        $asMetric = $attrs[0]->newInstance();

        return $asMetric->key;
    }

    /**
     * Generate a per-call idempotency key for metric events. Unlike exposures
     * (one per unit per experiment), conversions may be counted multiple times,
     * so we incorporate microsecond time to make each call unique while still
     * providing enough entropy to prevent double-fires within the same syscall.
     */
    private function metricIdempotencyKey(string $experimentKey, string $metricKey): string
    {
        return "metric:$experimentKey:$metricKey:{$this->unit->bucketingKey()}:" . hrtime(true);
    }
}
