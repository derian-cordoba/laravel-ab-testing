<?php

declare(strict_types=1);

namespace ABTests;

use ABTests\Attributes\AsMetric;
use ABTests\Contracts\AssignmentRepository;
use ABTests\Contracts\Bucketable;
use ABTests\Contracts\BucketingStrategy;
use ABTests\Contracts\EventSink;
use ABTests\Contracts\ExperimentStateRepository;
use ABTests\Contracts\ResolvesVariant;
use ABTests\Contracts\Variant;
use ABTests\Definitions\ExperimentDefinition;
use ABTests\Enums\EvaluationReason;
use ABTests\Enums\Environment;
use ABTests\Enums\EventType;
use ABTests\Application\Registry\ExperimentRegistry;
use ABTests\Values\Assignment;
use ABTests\Values\EvaluationResult;
use ABTests\Values\RecordedEvent;
use DateTimeImmutable;
use ReflectionClass;

/**
 * Fluent per-unit handle returned by Experiments::for($unit). Bundles the unit
 * with all the services needed to resolve variants, record exposures, and track
 * conversions/metrics. One instance per unit per request; never cached.
 *
 * API overview:
 *
 *   variant()      — resolve and immediately record an exposure (legacy convenience)
 *   expose()       — resolve and immediately record an exposure (explicit intent)
 *   resolve()      — resolve without recording exposure; returns EvaluationResult
 *   peek()         — return existing assignment without creating a new one
 *   isEligible()   — check audience + traffic eligibility without assigning
 *   assignment()   — return the persisted assignment (if any) for the unit
 */
final readonly class ExperimentResolver
{
    public function __construct(
        private Bucketable $unit,
        private ExperimentRegistry $registry,
        private ResolvesVariant $resolver,
        private EventSink $eventSink,
        private AssignmentRepository $assignmentRepository,
        private ExperimentStateRepository $stateRepository,
        private BucketingStrategy $bucketingStrategy,
    ) {
        //
    }

    // =========================================================================
    // Primary resolution API
    // =========================================================================

    /**
     * Resolve the experiment and record an exposure event. Returns null when the
     * unit is not eligible or assigned.
     *
     * This is the recommended method when the variant is definitely shown to the
     * unit at the call site. Use resolve() when you need to decouple resolution
     * from exposure recording.
     *
     * @param class-string<Experiment> $experimentClass
     */
    public function expose(string $experimentClass): ?Variant
    {
        $definition = $this->registry->findByClass($experimentClass);
        $result     = $this->resolver->resolve($definition, $this->unit);

        if ($result->variant !== null) {
            $this->recordExposureEvent($definition, $result->variant);
        }

        return $result->variant;
    }

    /**
     * Resolve the experiment without recording an exposure. Returns a rich
     * EvaluationResult that carries the variant, the reason for the outcome,
     * and an expose() method to explicitly trigger the exposure event later.
     *
     * Use this when the variant may be resolved before the unit actually sees
     * the experiment (e.g. middleware, DTOs, server-side rendering).
     *
     * @param class-string<Experiment> $experimentClass
     */
    public function resolve(string $experimentClass): EvaluationResult
    {
        $definition = $this->registry->findByClass($experimentClass);
        $result     = $this->resolver->resolve($definition, $this->unit);

        if ($result->variant === null) {
            return $result;
        }

        return $result->withExposeCallback(
            fn () => $this->recordExposureEvent($definition, $result->variant),
        );
    }

    /**
     * Return the existing assignment for the unit without running the full
     * resolution pipeline and without creating a new assignment.
     *
     * Returns an EvaluationResult with reason === stickyAssignment when an
     * assignment exists, or reason === noAssignment otherwise. The result's
     * expose() method will record an exposure if a variant is present.
     *
     * @param class-string<Experiment> $experimentClass
     */
    public function peek(string $experimentClass): EvaluationResult
    {
        $definition = $this->registry->findByClass($experimentClass);
        $assignment = $this->assignmentRepository->findAssignment(
            experimentKey: $definition->key,
            unitType: $definition->unitType,
            unitKey: $this->unit->bucketingKey(),
        );

        if ($assignment === null) {
            return new EvaluationResult(
                variant: null,
                reason: EvaluationReason::noAssignment,
                eligible: false,
                assigned: false,
                exposed: false,
                bucket: 0,
                matchedCriterion: null,
            );
        }

        $variant = $definition->allocation->findVariantByKey($assignment->variantKey);

        $result = new EvaluationResult(
            variant: $variant,
            reason: EvaluationReason::stickyAssignment,
            eligible: true,
            assigned: $variant !== null,
            exposed: false,
            bucket: 0,
            matchedCriterion: null,
        );

        if ($variant !== null) {
            return $result->withExposeCallback(
                fn () => $this->recordExposureEvent($definition, $variant),
            );
        }

        return $result;
    }

    /**
     * Check whether the unit is eligible for the experiment (passes the active,
     * environment, audience, and traffic gates) without creating an assignment.
     *
     * This never writes to the database. Use this for feature-gating decisions
     * that should not influence experimental analysis.
     *
     * @param class-string<Experiment> $experimentClass
     */
    public function isEligible(string $experimentClass): bool
    {
        $definition = $this->registry->findByClass($experimentClass);
        $state      = $this->stateRepository->findState($definition->key);

        if ($state === null || ! $state->isActive()) {
            return false;
        }

        // Environment gate (mirrors CheckEnvironmentStep).
        $allowed = $state->allowedEnvironments;

        if ($allowed !== null) {
            if ($allowed === []) {
                return false;
            }

            $current = Environment::tryFrom((string) app()->environment());

            if ($current === null || ! in_array($current->value, $allowed, true)) {
                return false;
            }
        }

        // Audience segment gate (mirrors CheckSegmentStep).
        if (! $definition->audience->matches($this->unit)) {
            return false;
        }

        // Traffic allocation gate (mirrors CheckTrafficAllocationStep).
        $position = $this->bucketingStrategy->position($definition->key, $this->unit);

        return $position < ($state->trafficPercentage / 100);
    }

    /**
     * Return the persisted assignment for this unit on the given experiment,
     * or null if no assignment has been created yet.
     *
     * Does not run the resolution pipeline and does not create assignments.
     *
     * @param class-string<Experiment> $experimentClass
     */
    public function assignment(string $experimentClass): ?Assignment
    {
        $definition = $this->registry->findByClass($experimentClass);

        return $this->assignmentRepository->findAssignment(
            experimentKey: $definition->key,
            unitType: $definition->unitType,
            unitKey: $this->unit->bucketingKey(),
        );
    }

    // =========================================================================
    // Legacy convenience method (kept for backwards compatibility)
    // =========================================================================

    /**
     * Resolve the variant assigned to the unit and record an exposure event.
     * Returns null when the unit is not eligible.
     *
     * Equivalent to expose(). Prefer expose() in new code to make the intent
     * explicit.
     *
     * @param class-string<Experiment> $experimentClass
     */
    public function variant(string $experimentClass): ?Variant
    {
        return $this->expose($experimentClass);
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
        $result     = $this->resolver->resolve($definition, $this->unit);

        if ($result->variant !== null) {
            $this->recordExposureEvent($definition, $result->variant);
        }

        return $result->variant;
    }

    // =========================================================================
    // Metric tracking
    // =========================================================================

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

    // =========================================================================
    // Private helpers
    // =========================================================================

    private function recordExposureEvent(ExperimentDefinition $definition, Variant $variant): void
    {
        $this->eventSink->record(new RecordedEvent(
            experimentKey: $definition->key,
            unitType: $definition->unitType,
            unitKey: $this->unit->bucketingKey(),
            variantKey: $variant->key(),
            type: EventType::exposure,
            idempotencyKey: "exposure:{$definition->key}:{$this->unit->bucketingKey()}",
            occurredAt: new DateTimeImmutable(),
        ));
    }

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
        $attrs     = $reflector->getAttributes(AsMetric::class);

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
