<?php

declare(strict_types=1);

namespace ABTests\Tests\Unit\Values;

use ABTests\Enums\EvaluationReason;
use ABTests\Tests\Fixtures\TestVariant;
use ABTests\Values\EvaluationResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EvaluationResultTest extends TestCase
{
    // -------------------------------------------------------------------------
    // expose() — fires the callback and returns an exposed copy
    // -------------------------------------------------------------------------

    #[Test]
    public function expose_invokes_the_callback_and_returns_new_instance_with_exposed_true(): void
    {
        $called = 0;

        $result = new EvaluationResult(
            variant: TestVariant::treatment,
            reason: EvaluationReason::newAssignment,
            eligible: true,
            assigned: true,
            exposed: false,
            bucket: 5000,
            matchedCriterion: null,
            exposeCallback: static function () use (&$called): void { $called++; },
        );

        $exposed = $result->expose();

        self::assertSame(1, $called);
        self::assertFalse($result->exposed, 'Original instance must remain immutable');
        self::assertTrue($exposed->exposed);
        self::assertSame(TestVariant::treatment, $exposed->variant);
        self::assertSame(EvaluationReason::newAssignment, $exposed->reason);
    }

    #[Test]
    public function expose_is_a_no_op_when_variant_is_null(): void
    {
        $called = 0;

        $result = new EvaluationResult(
            variant: null,
            reason: EvaluationReason::notInAudience,
            eligible: false,
            assigned: false,
            exposed: false,
            bucket: 3000,
            matchedCriterion: null,
            exposeCallback: static function () use (&$called): void { $called++; },
        );

        $same = $result->expose();

        self::assertSame(0, $called, 'Callback must not fire when variant is null');
        self::assertSame($result, $same, 'expose() must return the same instance when no-op');
    }

    #[Test]
    public function expose_is_a_no_op_when_no_callback_is_set(): void
    {
        $result = new EvaluationResult(
            variant: TestVariant::control,
            reason: EvaluationReason::stickyAssignment,
            eligible: true,
            assigned: true,
            exposed: false,
            bucket: 100,
            matchedCriterion: null,
        );

        $same = $result->expose();

        self::assertSame($result, $same);
        self::assertFalse($same->exposed);
    }

    #[Test]
    public function expose_can_be_called_multiple_times_without_duplicating_side_effects(): void
    {
        $called = 0;

        $result = new EvaluationResult(
            variant: TestVariant::treatment,
            reason: EvaluationReason::newAssignment,
            eligible: true,
            assigned: true,
            exposed: false,
            bucket: 7500,
            matchedCriterion: null,
            exposeCallback: static function () use (&$called): void { $called++; },
        );

        $first  = $result->expose();
        $second = $first->expose();

        // The callback fires on each call — idempotency is enforced by the
        // EventSink's unique idempotency key, not by EvaluationResult itself.
        self::assertSame(2, $called);
        self::assertTrue($second->exposed);
    }

    // -------------------------------------------------------------------------
    // withExposeCallback() — injects callback without firing it
    // -------------------------------------------------------------------------

    #[Test]
    public function with_expose_callback_returns_new_instance_without_firing_callback(): void
    {
        $called = 0;

        $result = new EvaluationResult(
            variant: TestVariant::control,
            reason: EvaluationReason::stickyAssignment,
            eligible: true,
            assigned: true,
            exposed: false,
            bucket: 2000,
            matchedCriterion: null,
        );

        $withCallback = $result->withExposeCallback(
            static function () use (&$called): void { $called++; },
        );

        self::assertSame(0, $called, 'withExposeCallback must not fire the callback');
        self::assertFalse($withCallback->exposed);
        self::assertSame(TestVariant::control, $withCallback->variant);
        self::assertNotSame($result, $withCallback);
    }

    // -------------------------------------------------------------------------
    // EvaluationReason helpers
    // -------------------------------------------------------------------------

    #[Test]
    public function is_assigned_returns_true_for_success_reasons(): void
    {
        self::assertTrue(EvaluationReason::stickyAssignment->isAssigned());
        self::assertTrue(EvaluationReason::newAssignment->isAssigned());
        self::assertTrue(EvaluationReason::override->isAssigned());
    }

    #[Test]
    public function is_assigned_returns_false_for_failure_reasons(): void
    {
        self::assertFalse(EvaluationReason::experimentNotRunning->isAssigned());
        self::assertFalse(EvaluationReason::experimentKilled->isAssigned());
        self::assertFalse(EvaluationReason::environmentRestricted->isAssigned());
        self::assertFalse(EvaluationReason::notInAudience->isAssigned());
        self::assertFalse(EvaluationReason::outsideTrafficAllocation->isAssigned());
        self::assertFalse(EvaluationReason::layerExcluded->isAssigned());
        self::assertFalse(EvaluationReason::noAssignment->isAssigned());
    }

    #[Test]
    public function is_eligible_returns_true_only_for_reasons_that_passed_all_gates(): void
    {
        self::assertTrue(EvaluationReason::layerExcluded->isEligible());
        self::assertTrue(EvaluationReason::stickyAssignment->isEligible());
        self::assertTrue(EvaluationReason::newAssignment->isEligible());
        self::assertTrue(EvaluationReason::override->isEligible());

        self::assertFalse(EvaluationReason::experimentNotRunning->isEligible());
        self::assertFalse(EvaluationReason::experimentKilled->isEligible());
        self::assertFalse(EvaluationReason::environmentRestricted->isEligible());
        self::assertFalse(EvaluationReason::notInAudience->isEligible());
        self::assertFalse(EvaluationReason::outsideTrafficAllocation->isEligible());
        self::assertFalse(EvaluationReason::noAssignment->isEligible());
    }
}
