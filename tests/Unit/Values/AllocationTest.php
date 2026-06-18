<?php

declare(strict_types=1);

namespace ABTests\Tests\Unit\Values;

use ABTests\Exceptions\InvalidAllocation;
use ABTests\Tests\Fixtures\TestVariant;
use ABTests\Tests\Fixtures\ThreeWayVariant;
use ABTests\Values\Allocation;
use ABTests\Values\GenericVariant;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AllocationTest extends TestCase
{
    #[Test]
    public function creates_from_valid_variants(): void
    {
        $allocation = Allocation::fromVariants(TestVariant::cases());

        self::assertCount(2, $allocation->variants);
    }

    #[Test]
    public function rejects_empty_variant_list(): void
    {
        $this->expectException(InvalidAllocation::class);
        Allocation::fromVariants([]);
    }

    #[Test]
    public function rejects_weights_that_do_not_sum_to_100(): void
    {
        $this->expectException(InvalidAllocation::class);
        Allocation::fromVariants([
            new GenericVariant('control', 40, true),
            new GenericVariant('treatment', 40),
        ]);
    }

    #[Test]
    public function rejects_missing_control(): void
    {
        $this->expectException(InvalidAllocation::class);
        Allocation::fromVariants([
            new GenericVariant('a', 50),
            new GenericVariant('b', 50),
        ]);
    }

    #[Test]
    public function rejects_multiple_controls(): void
    {
        $this->expectException(InvalidAllocation::class);
        Allocation::fromVariants([
            new GenericVariant('a', 50, true),
            new GenericVariant('b', 50, true),
        ]);
    }

    #[Test]
    public function variant_at_maps_position_to_first_bucket(): void
    {
        $allocation = Allocation::fromVariants(TestVariant::cases());

        // control: [0, 0.50), treatment: [0.50, 1.00)
        $variant = $allocation->variantAt(0.0);
        self::assertSame(TestVariant::control, $variant);
    }

    #[Test]
    public function variant_at_maps_position_to_second_bucket(): void
    {
        $allocation = Allocation::fromVariants(TestVariant::cases());

        $variant = $allocation->variantAt(0.75);
        self::assertSame(TestVariant::treatment, $variant);
    }

    #[Test]
    public function variant_at_assigns_last_variant_for_edge_position(): void
    {
        $allocation = Allocation::fromVariants(TestVariant::cases());

        // Position right at boundary maps into treatment
        $variant = $allocation->variantAt(0.5);
        self::assertSame(TestVariant::treatment, $variant);
    }

    #[Test]
    public function variant_at_handles_three_way_split(): void
    {
        $allocation = Allocation::fromVariants(ThreeWayVariant::cases());

        // control: [0, 0.34), variantA: [0.34, 0.67), variantB: [0.67, 1.00)
        self::assertSame(ThreeWayVariant::control, $allocation->variantAt(0.10));
        self::assertSame(ThreeWayVariant::variantA, $allocation->variantAt(0.50));
        self::assertSame(ThreeWayVariant::variantB, $allocation->variantAt(0.80));
    }

    #[Test]
    public function find_variant_by_key_returns_matching_variant(): void
    {
        $allocation = Allocation::fromVariants(TestVariant::cases());

        $variant = $allocation->findVariantByKey('treatment');
        self::assertSame(TestVariant::treatment, $variant);
    }

    #[Test]
    public function find_variant_by_key_returns_null_for_unknown_key(): void
    {
        $allocation = Allocation::fromVariants(TestVariant::cases());

        self::assertNull($allocation->findVariantByKey('nonexistent'));
    }

    #[Test]
    public function control_returns_the_control_variant(): void
    {
        $allocation = Allocation::fromVariants(TestVariant::cases());

        self::assertSame(TestVariant::control, $allocation->control());
    }
}
