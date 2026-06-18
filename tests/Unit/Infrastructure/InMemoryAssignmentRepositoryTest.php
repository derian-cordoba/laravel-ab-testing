<?php

declare(strict_types=1);

namespace ABTests\Tests\Unit\Infrastructure;

use ABTests\Infrastructure\InMemoryAssignmentRepository;
use ABTests\Values\Assignment;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InMemoryAssignmentRepositoryTest extends TestCase
{
    private InMemoryAssignmentRepository $repo;

    protected function setUp(): void
    {
        $this->repo = new InMemoryAssignmentRepository();
    }

    private function makeAssignment(
        string $experimentKey = 'exp',
        string $unitType = 'user',
        string $unitKey = 'user-1',
        string $variantKey = 'treatment',
        ?string $layer = null,
    ): Assignment {
        return new Assignment(
            experimentKey: $experimentKey,
            unitType: $unitType,
            unitKey: $unitKey,
            variantKey: $variantKey,
            layer: $layer,
            assignedAt: new DateTimeImmutable(),
        );
    }

    #[Test]
    public function find_returns_null_before_any_assignment(): void
    {
        self::assertNull($this->repo->findAssignment('exp', 'user', 'user-1'));
    }

    #[Test]
    public function store_and_find_assignment(): void
    {
        $assignment = $this->makeAssignment();
        $this->repo->storeAssignment($assignment);

        $found = $this->repo->findAssignment('exp', 'user', 'user-1');

        self::assertNotNull($found);
        self::assertSame('treatment', $found->variantKey);
    }

    #[Test]
    public function store_is_idempotent_first_write_wins(): void
    {
        $first = $this->makeAssignment(variantKey: 'treatment');
        $second = $this->makeAssignment(variantKey: 'control');

        $this->repo->storeAssignment($first);
        $this->repo->storeAssignment($second);

        $found = $this->repo->findAssignment('exp', 'user', 'user-1');

        self::assertSame('treatment', $found?->variantKey);
    }

    #[Test]
    public function find_by_layer_returns_null_when_no_assignment(): void
    {
        self::assertNull($this->repo->findAssignmentByLayer('checkout', 'user', 'user-1'));
    }

    #[Test]
    public function find_by_layer_returns_assignment_with_matching_layer(): void
    {
        $assignment = $this->makeAssignment(layer: 'checkout');
        $this->repo->storeAssignment($assignment);

        $found = $this->repo->findAssignmentByLayer('checkout', 'user', 'user-1');

        self::assertNotNull($found);
        self::assertSame('exp', $found->experimentKey);
    }

    #[Test]
    public function different_units_stored_independently(): void
    {
        $this->repo->storeAssignment($this->makeAssignment(unitKey: 'user-1', variantKey: 'control'));
        $this->repo->storeAssignment($this->makeAssignment(unitKey: 'user-2', variantKey: 'treatment'));

        self::assertSame('control', $this->repo->findAssignment('exp', 'user', 'user-1')?->variantKey);
        self::assertSame('treatment', $this->repo->findAssignment('exp', 'user', 'user-2')?->variantKey);
    }

    #[Test]
    public function flush_removes_all_assignments(): void
    {
        $this->repo->storeAssignment($this->makeAssignment(layer: 'checkout'));

        $this->repo->flush();

        self::assertNull($this->repo->findAssignment('exp', 'user', 'user-1'));
        self::assertNull($this->repo->findAssignmentByLayer('checkout', 'user', 'user-1'));
    }
}
