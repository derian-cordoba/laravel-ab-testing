<?php

declare(strict_types=1);

namespace ABTests\Tests\Integration\Infrastructure;

use ABTests\Infrastructure\Database\DatabaseAssignmentRepository;
use ABTests\Tests\Integration\DatabaseTestCase;
use ABTests\Values\Assignment;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;

final class DatabaseAssignmentRepositoryTest extends DatabaseTestCase
{
    private DatabaseAssignmentRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new DatabaseAssignmentRepository();
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
        $this->repo->storeAssignment($this->makeAssignment());

        $found = $this->repo->findAssignment('exp', 'user', 'user-1');

        self::assertNotNull($found);
        self::assertSame('treatment', $found->variantKey);
        self::assertSame('exp', $found->experimentKey);
        self::assertSame('user', $found->unitType);
        self::assertSame('user-1', $found->unitKey);
    }

    #[Test]
    public function store_is_idempotent_first_write_wins(): void
    {
        $this->repo->storeAssignment($this->makeAssignment(variantKey: 'treatment'));
        $this->repo->storeAssignment($this->makeAssignment(variantKey: 'control'));

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
        $this->repo->storeAssignment($this->makeAssignment(layer: 'checkout'));

        $found = $this->repo->findAssignmentByLayer('checkout', 'user', 'user-1');

        self::assertNotNull($found);
        self::assertSame('exp', $found->experimentKey);
        self::assertSame('checkout', $found->layer);
    }

    #[Test]
    public function find_by_layer_returns_null_for_different_layer(): void
    {
        $this->repo->storeAssignment($this->makeAssignment(layer: 'checkout'));

        self::assertNull($this->repo->findAssignmentByLayer('pricing', 'user', 'user-1'));
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
    public function different_experiments_stored_independently(): void
    {
        $this->repo->storeAssignment($this->makeAssignment(experimentKey: 'exp-a', variantKey: 'control'));
        $this->repo->storeAssignment($this->makeAssignment(experimentKey: 'exp-b', variantKey: 'treatment'));

        self::assertSame('control', $this->repo->findAssignment('exp-a', 'user', 'user-1')?->variantKey);
        self::assertSame('treatment', $this->repo->findAssignment('exp-b', 'user', 'user-1')?->variantKey);
    }

    #[Test]
    public function assignment_hydrates_assigned_at_as_date_time_immutable(): void
    {
        $this->repo->storeAssignment($this->makeAssignment());

        $found = $this->repo->findAssignment('exp', 'user', 'user-1');

        self::assertInstanceOf(DateTimeImmutable::class, $found?->assignedAt);
    }

    #[Test]
    public function layer_field_is_null_when_no_layer_set(): void
    {
        $this->repo->storeAssignment($this->makeAssignment(layer: null));

        $found = $this->repo->findAssignment('exp', 'user', 'user-1');

        self::assertNull($found?->layer);
    }
}
