<?php

declare(strict_types=1);

namespace ABTests\Values;

use ABTests\Contracts\Bucketable;
use ABTests\Enums\Operator;

/**
 * A reusable, immutable audience definition built from a conjunction of
 * criteria. Returned from an experiment's audience() method to scope who is
 * eligible. Every with*()/and() call returns a new instance.
 */
final readonly class Segment
{
    /**
     * @param  list<Criterion>  $criteria
     */
    private function __construct(public array $criteria)
    {
        //
    }

    /** Everyone is eligible. */
    public static function any(): self
    {
        return new self([]);
    }

    public static function where(
        string $attribute,
        mixed $value,
        Operator $operator = Operator::equals,
    ): self {
        return self::any()->and($attribute, $value, $operator);
    }

    public function and(
        string $attribute,
        mixed $value,
        Operator $operator = Operator::equals,
    ): self {
        return new self([
            ...$this->criteria,
            new Criterion($attribute, $operator, $value),
        ]);
    }

    public function matches(Bucketable $unit): bool
    {
        $attributes = $unit->attributes();

        return array_all(
            $this->criteria,
            static fn (Criterion $criterion): bool => $criterion->matches($attributes),
        );
    }
}
