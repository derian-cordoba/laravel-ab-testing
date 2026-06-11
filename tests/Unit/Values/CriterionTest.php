<?php

declare(strict_types=1);

namespace ABTests\Tests\Unit\Values;

use ABTests\Enums\Operator;
use ABTests\Values\Criterion;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CriterionTest extends TestCase
{
    /** @return array<string, array{Operator, mixed, mixed, bool}> */
    public static function matchProvider(): array
    {
        return [
            'equals — match'              => [Operator::equals,      'pro',          'pro',            true],
            'equals — no match'           => [Operator::equals,      'pro',          'free',           false],
            'notEquals — match'           => [Operator::notEquals,   'pro',          'free',           true],
            'notEquals — no match'        => [Operator::notEquals,   'pro',          'pro',            false],
            'in — match'                  => [Operator::in,          ['US', 'CA'],   'US',             true],
            'in — no match'               => [Operator::in,          ['US', 'CA'],   'GB',             false],
            'notIn — match'               => [Operator::notIn,       ['US', 'CA'],   'GB',             true],
            'notIn — no match'            => [Operator::notIn,       ['US', 'CA'],   'US',             false],
            'greaterThan — match'         => [Operator::greaterThan, 10,             15,               true],
            'greaterThan — no match'      => [Operator::greaterThan, 10,             5,                false],
            'greaterThan — null actual'   => [Operator::greaterThan, 10,             null,             false],
            'lessThan — match'            => [Operator::lessThan,    10,             5,                true],
            'lessThan — no match'         => [Operator::lessThan,    10,             15,               false],
            'lessThan — null actual'      => [Operator::lessThan,    10,             null,             false],
        ];
    }

    #[Test]
    #[DataProvider('matchProvider')]
    public function evaluates_operator_correctly(
        Operator $operator,
        mixed $expected,
        mixed $actual,
        bool $shouldMatch,
    ): void {
        $criterion = new Criterion('attr', $operator, $expected);
        self::assertSame($shouldMatch, $criterion->matches(['attr' => $actual]));
    }

    #[Test]
    public function missing_attribute_is_treated_as_null(): void
    {
        $criterion = new Criterion('plan', Operator::equals, 'pro');

        self::assertFalse($criterion->matches([]));
    }
}
