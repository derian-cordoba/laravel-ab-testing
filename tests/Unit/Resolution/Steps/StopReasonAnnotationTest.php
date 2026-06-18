<?php

declare(strict_types=1);

namespace ABTests\Tests\Unit\Resolution\Steps;

use ABTests\Application\Resolution\Steps\BucketStep;
use ABTests\Application\Resolution\Steps\CheckExperimentActiveStep;
use ABTests\Application\Resolution\Steps\CheckLayerExclusionStep;
use ABTests\Application\Resolution\Steps\CheckSegmentStep;
use ABTests\Application\Resolution\Steps\CheckTrafficAllocationStep;
use ABTests\Application\Resolution\Steps\LoadExistingAssignmentStep;
use ABTests\Enums\EvaluationReason;
use ABTests\Enums\ExperimentStatus;
use ABTests\Infrastructure\InMemoryAssignmentRepository;
use ABTests\Tests\Fixtures\TestUnit;
use ABTests\Tests\Fixtures\TestVariant;
use ABTests\Tests\Support\MakesPayload;
use ABTests\Values\Assignment;
use ABTests\Values\ExperimentState;
use ABTests\Values\Segment;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Verifies that each resolution step annotates the correct EvaluationReason
 * on the payload when it short-circuits (returns false) or succeeds.
 */
final class StopReasonAnnotationTest extends TestCase
{
    use MakesPayload;

    // -------------------------------------------------------------------------
    // CheckExperimentActiveStep
    // -------------------------------------------------------------------------

    #[Test]
    public function check_experiment_active_sets_killed_reason_when_kill_switch_is_on(): void
    {
        $state = new ExperimentState('exp', ExperimentStatus::running, 100, isKilled: true);
        $payload = $this->makePayload(state: $state);

        (new CheckExperimentActiveStep())->handle($payload);

        self::assertSame(EvaluationReason::experimentKilled, $payload->stopReason);
    }

    #[Test]
    public function check_experiment_active_sets_not_running_reason_when_paused(): void
    {
        $state = new ExperimentState('exp', ExperimentStatus::paused, 100);
        $payload = $this->makePayload(state: $state);

        (new CheckExperimentActiveStep())->handle($payload);

        self::assertSame(EvaluationReason::experimentNotRunning, $payload->stopReason);
    }

    #[Test]
    public function check_experiment_active_leaves_reason_null_when_running(): void
    {
        $state = ExperimentState::alwaysRunning('exp');
        $payload = $this->makePayload(state: $state);

        (new CheckExperimentActiveStep())->handle($payload);

        self::assertNull($payload->stopReason);
    }

    // -------------------------------------------------------------------------
    // CheckSegmentStep
    // -------------------------------------------------------------------------

    #[Test]
    public function check_segment_sets_not_in_audience_reason_when_unit_does_not_match(): void
    {
        $definition = $this->makeDefinition(audience: Segment::where('plan', 'pro'));
        $unit = new TestUnit(attributes: ['plan' => 'free']);
        $payload = $this->makePayload(definition: $definition, unit: $unit);

        (new CheckSegmentStep())->handle($payload);

        self::assertSame(EvaluationReason::notInAudience, $payload->stopReason);
    }

    #[Test]
    public function check_segment_leaves_reason_null_when_unit_matches(): void
    {
        $definition = $this->makeDefinition(audience: Segment::where('plan', 'pro'));
        $unit = new TestUnit(attributes: ['plan' => 'pro']);
        $payload = $this->makePayload(definition: $definition, unit: $unit);

        (new CheckSegmentStep())->handle($payload);

        self::assertNull($payload->stopReason);
    }

    // -------------------------------------------------------------------------
    // CheckTrafficAllocationStep
    // -------------------------------------------------------------------------

    #[Test]
    public function check_traffic_sets_outside_allocation_reason_when_unit_is_held_out(): void
    {
        $state = new ExperimentState('exp', ExperimentStatus::running, 10); // 10% traffic
        $payload = $this->makePayload(state: $state, bucketPosition: 0.5);      // position > 10%

        (new CheckTrafficAllocationStep())->handle($payload);

        self::assertSame(EvaluationReason::outsideTrafficAllocation, $payload->stopReason);
    }

    #[Test]
    public function check_traffic_leaves_reason_null_when_unit_is_in_traffic_slice(): void
    {
        $state = new ExperimentState('exp', ExperimentStatus::running, 80); // 80% traffic
        $payload = $this->makePayload(state: $state);     // position < 80%

        (new CheckTrafficAllocationStep())->handle($payload);

        self::assertNull($payload->stopReason);
    }

    // -------------------------------------------------------------------------
    // CheckLayerExclusionStep
    // -------------------------------------------------------------------------

    #[Test]
    public function check_layer_exclusion_sets_layer_excluded_reason_when_unit_is_in_another_experiment(): void
    {
        $assignmentRepository = new InMemoryAssignmentRepository();
        $definition = $this->makeDefinition(key: 'exp-a', layer: 'checkout');

        // Pre-seed an assignment for a DIFFERENT experiment in the same layer.
        $assignmentRepository->storeAssignment(new Assignment(
            experimentKey: 'exp-b',
            unitType: 'test-user',
            unitKey: 'unit-1',
            variantKey: 'control',
            layer: 'checkout',
            assignedAt: new DateTimeImmutable(),
        ));

        $payload = $this->makePayload(definition: $definition);

        (new CheckLayerExclusionStep($assignmentRepository))->handle($payload);

        self::assertSame(EvaluationReason::layerExcluded, $payload->stopReason);
    }

    #[Test]
    public function check_layer_exclusion_leaves_reason_null_when_no_layer_conflict(): void
    {
        $assignmentRepository = new InMemoryAssignmentRepository();
        $definition = $this->makeDefinition(key: 'exp-a', layer: 'checkout');
        $payload = $this->makePayload(definition: $definition);

        (new CheckLayerExclusionStep($assignmentRepository))->handle($payload);

        self::assertNull($payload->stopReason);
    }

    // -------------------------------------------------------------------------
    // LoadExistingAssignmentStep — annotates stickyAssignment on success
    // -------------------------------------------------------------------------

    #[Test]
    public function load_existing_assignment_sets_sticky_reason_when_assignment_found(): void
    {
        $assignmentRepository = new InMemoryAssignmentRepository();
        $definition = $this->makeDefinition();
        $payload = $this->makePayload(definition: $definition);

        $assignmentRepository->storeAssignment(new Assignment(
            experimentKey: $definition->key,
            unitType: $definition->unitType,
            unitKey: $payload->unit->bucketingKey(),
            variantKey: TestVariant::control->key(),
            layer: null,
            assignedAt: new DateTimeImmutable(),
        ));

        (new LoadExistingAssignmentStep($assignmentRepository))->handle($payload);

        self::assertSame(EvaluationReason::stickyAssignment, $payload->stopReason);
        self::assertTrue($payload->hasExistingAssignment);
        self::assertSame(TestVariant::control, $payload->resolvedVariant);
    }

    #[Test]
    public function load_existing_assignment_leaves_reason_null_when_no_assignment(): void
    {
        $assignmentRepository = new InMemoryAssignmentRepository();
        $payload = $this->makePayload();

        (new LoadExistingAssignmentStep($assignmentRepository))->handle($payload);

        self::assertNull($payload->stopReason);
        self::assertFalse($payload->hasExistingAssignment);
    }

    // -------------------------------------------------------------------------
    // BucketStep — annotates newAssignment on success
    // -------------------------------------------------------------------------

    #[Test]
    public function bucket_step_sets_new_assignment_reason(): void
    {
        $payload = $this->makePayload(bucketPosition: 0.1);

        (new BucketStep())->handle($payload);

        self::assertSame(EvaluationReason::newAssignment, $payload->stopReason);
        self::assertNotNull($payload->resolvedVariant);
    }

    #[Test]
    public function bucket_step_does_not_override_sticky_reason(): void
    {
        $payload = $this->makePayload(bucketPosition: 0.1);
        $payload->hasExistingAssignment = true;
        $payload->stopReason = EvaluationReason::stickyAssignment;

        (new BucketStep())->handle($payload);

        // BucketStep is skipped when hasExistingAssignment is true.
        self::assertSame(EvaluationReason::stickyAssignment, $payload->stopReason);
    }
}
