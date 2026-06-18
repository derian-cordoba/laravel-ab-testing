<?php

declare(strict_types=1);

namespace ABTests\Tests\Unit;

use ABTests\Enums\EvaluationReason;
use ABTests\Enums\EventType;
use ABTests\Enums\ExperimentStatus;
use ABTests\ExperimentResolver;
use ABTests\Contracts\ExperimentStateRepository;
use ABTests\Infrastructure\AlwaysRunningExperimentStateRepository;
use ABTests\Infrastructure\InMemoryAssignmentRepository;
use ABTests\Infrastructure\Bucketing\Sha256BucketingStrategy;
use ABTests\Infrastructure\NullEventSink;
use ABTests\Application\Registry\AttributeReader;
use ABTests\Application\Registry\ExperimentRegistry;
use ABTests\Application\Resolution\Resolver;
use ABTests\Application\Resolution\Steps\BucketStep;
use ABTests\Application\Resolution\Steps\CheckExperimentActiveStep;
use ABTests\Application\Resolution\Steps\CheckEnvironmentStep;
use ABTests\Application\Resolution\Steps\CheckLayerExclusionStep;
use ABTests\Application\Resolution\Steps\CheckSegmentStep;
use ABTests\Application\Resolution\Steps\CheckTrafficAllocationStep;
use ABTests\Application\Resolution\Steps\LoadExistingAssignmentStep;
use ABTests\Application\Resolution\Steps\PersistAssignmentStep;
use ABTests\Testing\RecordingEventSink;
use ABTests\Tests\Fixtures\TestExperiment;
use ABTests\Tests\Fixtures\TestUnit;
use ABTests\Tests\Fixtures\TestVariant;
use ABTests\Values\Assignment;
use ABTests\Values\EvaluationResult;
use ABTests\Values\ExperimentState;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the new ExperimentResolver evaluation API:
 *   resolve()    — returns EvaluationResult without recording an exposure
 *   expose()     — resolves and records an exposure, returns ?Variant
 *   peek()       — returns existing assignment without running the full pipeline
 *   isEligible() — checks eligibility without assigning
 *   assignment() — returns the persisted assignment (if any)
 */
final class ExperimentResolverEvaluationTest extends TestCase
{
    private ExperimentRegistry $registry;
    private InMemoryAssignmentRepository $assignmentRepository;
    private AlwaysRunningExperimentStateRepository $stateRepository;
    private Sha256BucketingStrategy $bucketingStrategy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = new ExperimentRegistry();
        $reader         = new AttributeReader();
        $definition     = $reader->readExperiment(TestExperiment::class);
        $this->registry->register($definition, TestExperiment::class);

        $this->assignmentRepository = new InMemoryAssignmentRepository();
        $this->stateRepository      = new AlwaysRunningExperimentStateRepository();
        $this->bucketingStrategy    = new Sha256BucketingStrategy();
    }

    // =========================================================================
    // resolve() — returns EvaluationResult, no exposure recorded
    // =========================================================================

    #[Test]
    public function resolve_returns_evaluation_result_with_assigned_variant(): void
    {
        $resolver = $this->makeResolver();

        $result = $resolver->resolve(TestExperiment::class);

        self::assertNotNull($result->variant);
        self::assertTrue($result->assigned);
        self::assertTrue($result->eligible);
        self::assertFalse($result->exposed);
        self::assertContains($result->reason, [
            EvaluationReason::newAssignment,
            EvaluationReason::stickyAssignment,
        ]);
    }

    #[Test]
    public function resolve_does_not_record_exposure_event(): void
    {
        $eventSink = new RecordingEventSink();
        $resolver  = $this->makeResolver(eventSink: $eventSink);

        $resolver->resolve(TestExperiment::class);

        self::assertCount(0, $eventSink->ofType(EventType::exposure), 'resolve() must not record any events');
    }

    #[Test]
    public function resolve_returns_null_variant_when_experiment_is_paused(): void
    {
        $stateRepo = new class implements ExperimentStateRepository {
            public function findState(string $experimentKey): ?ExperimentState
            {
                return new ExperimentState(
                    experimentKey: $experimentKey,
                    status: ExperimentStatus::paused,
                    trafficPercentage: 100,
                );
            }
        };

        $resolver = $this->makeResolver(stateRepository: $stateRepo);
        $result   = $resolver->resolve(TestExperiment::class);

        self::assertNull($result->variant);
        self::assertFalse($result->assigned);
        self::assertFalse($result->eligible);
        self::assertSame(EvaluationReason::experimentNotRunning, $result->reason);
    }

    // =========================================================================
    // resolve()->expose() — deferred exposure recording
    // =========================================================================

    #[Test]
    public function resolve_then_expose_records_the_exposure_event(): void
    {
        $eventSink = new RecordingEventSink();
        $resolver  = $this->makeResolver(eventSink: $eventSink);

        $evaluation = $resolver->resolve(TestExperiment::class);
        self::assertCount(0, $eventSink->ofType(EventType::exposure), 'No event before expose()');

        $evaluation->expose();
        self::assertCount(1, $eventSink->ofType(EventType::exposure), 'One exposure after expose()');
    }

    #[Test]
    public function resolve_expose_returns_new_result_with_exposed_true(): void
    {
        $resolver   = $this->makeResolver();
        $evaluation = $resolver->resolve(TestExperiment::class);

        self::assertFalse($evaluation->exposed);

        $exposed = $evaluation->expose();

        self::assertTrue($exposed->exposed);
        self::assertFalse($evaluation->exposed, 'Original must remain immutable');
    }

    // =========================================================================
    // expose() — convenience method: resolve + record exposure
    // =========================================================================

    #[Test]
    public function expose_records_exposure_and_returns_variant(): void
    {
        $eventSink = new RecordingEventSink();
        $resolver  = $this->makeResolver(eventSink: $eventSink);

        $variant = $resolver->expose(TestExperiment::class);

        self::assertNotNull($variant);
        self::assertCount(1, $eventSink->ofType(EventType::exposure));
    }

    #[Test]
    public function expose_returns_null_when_unit_not_assigned(): void
    {
        $stateRepo = new class implements ExperimentStateRepository {
            public function findState(string $experimentKey): ?ExperimentState
            {
                return new ExperimentState(
                    experimentKey: $experimentKey,
                    status: ExperimentStatus::paused,
                    trafficPercentage: 100,
                );
            }
        };

        $resolver = $this->makeResolver(stateRepository: $stateRepo);
        $variant  = $resolver->expose(TestExperiment::class);

        self::assertNull($variant);
    }

    // =========================================================================
    // peek() — existing assignment only, no new assignment created
    // =========================================================================

    #[Test]
    public function peek_returns_no_assignment_when_unit_has_no_assignment(): void
    {
        $resolver = $this->makeResolver(unitKey: 'user-never-assigned');
        $result   = $resolver->peek(TestExperiment::class);

        self::assertNull($result->variant);
        self::assertFalse($result->assigned);
        self::assertSame(EvaluationReason::noAssignment, $result->reason);
    }

    #[Test]
    public function peek_returns_existing_assignment_without_creating_new_one(): void
    {
        // Pre-seed an assignment.
        $definition = $this->registry->findByClass(TestExperiment::class);
        $this->assignmentRepository->storeAssignment(new Assignment(
            experimentKey: $definition->key,
            unitType: $definition->unitType,
            unitKey: 'user-peeked',
            variantKey: TestVariant::control->key(),
            layer: $definition->layer,
            assignedAt: new DateTimeImmutable(),
        ));

        $resolver = $this->makeResolver(unitKey: 'user-peeked');
        $result   = $resolver->peek(TestExperiment::class);

        self::assertSame(TestVariant::control, $result->variant);
        self::assertTrue($result->assigned);
        self::assertSame(EvaluationReason::stickyAssignment, $result->reason);
    }

    #[Test]
    public function peek_does_not_create_new_assignment(): void
    {
        $resolver = $this->makeResolver(unitKey: 'never-assigned');

        $resolver->peek(TestExperiment::class);

        $definition = $this->registry->findByClass(TestExperiment::class);
        $assignment = $this->assignmentRepository->findAssignment(
            experimentKey: $definition->key,
            unitType: $definition->unitType,
            unitKey: 'never-assigned',
        );

        self::assertNull($assignment, 'peek() must not create a new assignment');
    }

    #[Test]
    public function peek_expose_records_exposure_for_existing_assignment(): void
    {
        $definition = $this->registry->findByClass(TestExperiment::class);
        $this->assignmentRepository->storeAssignment(new Assignment(
            experimentKey: $definition->key,
            unitType: $definition->unitType,
            unitKey: 'user-peek-expose',
            variantKey: TestVariant::treatment->key(),
            layer: $definition->layer,
            assignedAt: new DateTimeImmutable(),
        ));

        $eventSink = new RecordingEventSink();
        $resolver  = $this->makeResolver(unitKey: 'user-peek-expose', eventSink: $eventSink);

        $result = $resolver->peek(TestExperiment::class);
        self::assertCount(0, $eventSink->ofType(EventType::exposure));

        $result->expose();
        self::assertCount(1, $eventSink->ofType(EventType::exposure));
    }

    // =========================================================================
    // isEligible() — check without assigning
    // =========================================================================

    #[Test]
    public function is_eligible_returns_true_when_experiment_is_running_and_audience_matches(): void
    {
        $resolver = $this->makeResolver(attributes: ['plan' => 'pro']);

        self::assertTrue($resolver->isEligible(TestExperiment::class));
    }

    #[Test]
    public function is_eligible_returns_false_when_unit_does_not_match_audience(): void
    {
        $resolver = $this->makeResolver(unitKey: 'user-free', attributes: ['plan' => 'free']);

        self::assertFalse($resolver->isEligible(TestExperiment::class));
    }

    #[Test]
    public function is_eligible_does_not_create_assignment(): void
    {
        $resolver = $this->makeResolver(unitKey: 'user-check', attributes: ['plan' => 'pro']);

        $resolver->isEligible(TestExperiment::class);

        $definition = $this->registry->findByClass(TestExperiment::class);
        $assignment = $this->assignmentRepository->findAssignment(
            experimentKey: $definition->key,
            unitType: $definition->unitType,
            unitKey: 'user-check',
        );

        self::assertNull($assignment, 'isEligible() must not create a new assignment');
    }

    #[Test]
    public function is_eligible_returns_false_when_experiment_is_not_running(): void
    {
        $stateRepo = new class implements ExperimentStateRepository {
            public function findState(string $experimentKey): ?ExperimentState
            {
                return new ExperimentState(
                    experimentKey: $experimentKey,
                    status: ExperimentStatus::paused,
                    trafficPercentage: 100,
                );
            }
        };

        $resolver = $this->makeResolver(
            stateRepository: $stateRepo,
        );

        self::assertFalse($resolver->isEligible(TestExperiment::class));
    }

    // =========================================================================
    // assignment() — look up persisted assignment
    // =========================================================================

    #[Test]
    public function assignment_returns_null_when_no_assignment_exists(): void
    {
        $resolver = $this->makeResolver(unitKey: 'user-none');

        self::assertNull($resolver->assignment(TestExperiment::class));
    }

    #[Test]
    public function assignment_returns_existing_assignment(): void
    {
        $definition = $this->registry->findByClass(TestExperiment::class);
        $this->assignmentRepository->storeAssignment(new Assignment(
            experimentKey: $definition->key,
            unitType: $definition->unitType,
            unitKey: 'user-assigned',
            variantKey: TestVariant::treatment->key(),
            layer: $definition->layer,
            assignedAt: new DateTimeImmutable(),
        ));

        $resolver   = $this->makeResolver(unitKey: 'user-assigned');
        $assignment = $resolver->assignment(TestExperiment::class);

        self::assertNotNull($assignment);
        self::assertSame(TestVariant::treatment->key(), $assignment->variantKey);
    }

    // =========================================================================
    // variant() — backwards-compatible legacy method
    // =========================================================================

    #[Test]
    public function variant_still_records_exposure_and_returns_variant(): void
    {
        $eventSink = new RecordingEventSink();
        $resolver  = $this->makeResolver(
            eventSink: $eventSink,
        );

        $variant = $resolver->variant(TestExperiment::class);

        self::assertNotNull($variant);
        self::assertCount(1, $eventSink->ofType(EventType::exposure), 'variant() must still record an exposure');
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeResolver(
        string $unitKey = 'user-1',
        array $attributes = ['plan' => 'pro'],
        ?RecordingEventSink $eventSink = null,
        ?ExperimentStateRepository $stateRepository = null,
    ): ExperimentResolver {
        $assignmentRepository = $this->assignmentRepository;
        $stateRepo            = $stateRepository ?? $this->stateRepository;

        $resolver = new Resolver(
            bucketingStrategy: $this->bucketingStrategy,
            stateRepository: $stateRepo,
            steps: [
                new CheckExperimentActiveStep(),
                new CheckEnvironmentStep(),
                new CheckSegmentStep(),
                new CheckTrafficAllocationStep(),
                new LoadExistingAssignmentStep($assignmentRepository),
                new CheckLayerExclusionStep($assignmentRepository),
                new BucketStep(),
                new PersistAssignmentStep($assignmentRepository),
            ],
        );

        return new ExperimentResolver(
            unit: new TestUnit($unitKey, $attributes),
            registry: $this->registry,
            resolver: $resolver,
            eventSink: $eventSink ?? new NullEventSink(),
            assignmentRepository: $assignmentRepository,
            stateRepository: $stateRepo,
            bucketingStrategy: $this->bucketingStrategy,
        );
    }
}
