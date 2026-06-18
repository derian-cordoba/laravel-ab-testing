<?php

declare(strict_types=1);

namespace ABTests\Values;

use ABTests\Contracts\Variant;
use ABTests\Enums\EvaluationReason;
use Closure;

/**
 * Rich result returned by ExperimentResolver::resolve(). Carries the resolved
 * variant alongside diagnostic information about why the pipeline produced this
 * result — useful for debugging, observability dashboards, and QA tooling.
 *
 * Usage:
 *
 *   $evaluation = Experiments::for($user)->resolve(CheckoutButtonColor::class);
 *   $variant    = $evaluation->variant;
 *
 *   // Record exposure explicitly when the variant is actually shown:
 *   $evaluation->expose();
 *
 * The result is immutable. Calling expose() returns a new instance with
 * exposed === true and fires the exposure event exactly once via the injected
 * callback (the EventSink's idempotency key deduplicates any further fires).
 */
final readonly class EvaluationResult
{
    /**
     * @param  Variant|null  $variant  The resolved variant, or null when the unit is not assigned.
     * @param  EvaluationReason  $reason  Why the pipeline produced this result.
     * @param  bool  $eligible  Whether the unit passed all targeting and traffic gates.
     * @param  bool  $assigned  Whether the unit has (or received) a variant assignment.
     * @param  bool  $exposed  Whether an exposure event has been recorded for this result.
     * @param  int  $bucket  The unit's bucket position scaled to [0, 9999].
     * @param  string|null  $matchedCriterion  The audience criterion that determined targeting (if available).
     * @param  Closure|null  $exposeCallback  Internal: injected by ExperimentResolver to record the exposure event.
     */
    public function __construct(
        public ?Variant $variant,
        public EvaluationReason $reason,
        public bool $eligible,
        public bool $assigned,
        public bool $exposed,
        public int $bucket,
        public ?string $matchedCriterion,
        private ?Closure $exposeCallback = null,
    ) {
        //
    }

    /**
     * Record an exposure event and return a new result with exposed === true.
     *
     * Safe to call when the unit has no variant (returns self unchanged).
     * Safe to call multiple times — the EventSink's idempotency key ensures
     * only the first call is written to the store.
     */
    public function expose(): self
    {
        if ($this->variant === null || $this->exposeCallback === null) {
            return $this;
        }

        ($this->exposeCallback)();

        return new self(
            variant: $this->variant,
            reason: $this->reason,
            eligible: $this->eligible,
            assigned: $this->assigned,
            exposed: true,
            bucket: $this->bucket,
            matchedCriterion: $this->matchedCriterion,
            exposeCallback: $this->exposeCallback,
        );
    }

    /**
     * Return a new instance with the exposure callback injected.
     * Called internally by ExperimentResolver after the pipeline runs.
     */
    public function withExposeCallback(Closure $callback): self
    {
        return new self(
            variant: $this->variant,
            reason: $this->reason,
            eligible: $this->eligible,
            assigned: $this->assigned,
            exposed: $this->exposed,
            bucket: $this->bucket,
            matchedCriterion: $this->matchedCriterion,
            exposeCallback: $callback,
        );
    }
}
