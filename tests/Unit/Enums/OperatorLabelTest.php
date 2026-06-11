<?php

declare(strict_types=1);

namespace ABTests\Tests\Unit\Enums;

use ABTests\Enums\Operator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class OperatorLabelTest extends TestCase
{
    #[Test]
    public function every_case_has_a_non_empty_label(): void
    {
        foreach (Operator::cases() as $operator) {
            self::assertNotEmpty($operator->label(), "Operator::{$operator->name} has an empty label.");
        }
    }

    /**
     * @return array<string, array{Operator, string}>
     */
    public static function labelProvider(): array
    {
        return [
            'equals'        => [Operator::equals,      'equals'],
            'notEquals'     => [Operator::notEquals,   'does not equal'],
            'in'            => [Operator::in,          'is one of'],
            'notIn'         => [Operator::notIn,       'is not one of'],
            'greaterThan'   => [Operator::greaterThan, 'greater than'],
            'lessThan'      => [Operator::lessThan,    'less than'],
        ];
    }

    #[Test]
    #[DataProvider('labelProvider')]
    public function label_returns_expected_string(Operator $operator, string $expected): void
    {
        self::assertSame($expected, $operator->label());
    }

    #[Test]
    public function all_six_cases_have_distinct_labels(): void
    {
        $labels = array_map(static fn (Operator $op): string => $op->label(), Operator::cases());

        self::assertCount(count(Operator::cases()), array_unique($labels));
    }
}
