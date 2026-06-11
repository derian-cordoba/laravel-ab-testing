<?php

declare(strict_types=1);

namespace ABTests\Contracts;

/**
 * A single arm of an experiment. Implemented by a backed enum that uses the
 * ABTests\Concerns\IsVariant trait, so the enum stays the type-safe,
 * exhaustively matchable source of truth for an experiment's arms.
 */
interface Variant
{
    /** The stable storage key for this variant (the enum's backing value). */
    public function key(): string;

    /** Allocation weight as a whole percentage. All weights must sum to 100. */
    public function weight(): int;

    /** Whether this arm is the baseline every other arm is measured against. */
    public function isControl(): bool;
}
