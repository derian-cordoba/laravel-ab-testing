<?php

declare(strict_types=1);

namespace ABTests\Values;

use ABTests\Enums\Operator;

/**
 * A single targeting rule: one unit attribute compared against an expected
 * value using one operator.
 */
final readonly class Criterion
{
    public function __construct(
        public string $attribute,
        public Operator $operator,
        public mixed $expected,
    ) {
        //
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function matches(array $attributes): bool
    {
        $actual = $attributes[$this->attribute] ?? null;

        return match ($this->operator) {
            Operator::equals => $actual === $this->expected,
            Operator::notEquals => $actual !== $this->expected,
            Operator::in => is_array($this->expected) && in_array($actual, $this->expected, true),
            Operator::notIn => is_array($this->expected) && ! in_array($actual, $this->expected, true),
            Operator::greaterThan => $actual !== null && $actual > $this->expected,
            Operator::lessThan => $actual !== null && $actual < $this->expected,
        };
    }
}
