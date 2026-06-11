<?php

declare(strict_types=1);

namespace ABTests\Tests\Unit\Resolution\Steps;

use ABTests\Infrastructure\InMemoryAssignmentRepository;
use ABTests\Resolution\Steps\LoadExistingAssignmentStep;
use ABTests\Tests\Fixtures\TestUnit;
use ABTests\Tests\Fixtures\TestVariant;
use ABTests\Tests\Support\MakesPayload;
use ABTests\Values\Assignment;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LoadExistingAssignmentStepTest extends TestCase
{
    use MakesPayload;

    private InMemoryAssignmentRepository $repo;
    private LoadExistingAssignmentStep $step;

    protected function setUp(): void
    {
        $this->repo = new InMemoryAssignmentRepository();
        $this->step = new LoadExistingAssignmentStep($this->repo);
    }

    #[Test]
    public function returns_true_and_does_nothing_when_no_existing_assignment(): void
    {
        $payload = $this->makePayload();

        $result = $this->step->handle($payload);

        self::assertTrue($result);
        self::assertNull($payload->resolvedVariant);
        self::assertFalse($payload->hasExistingAssignment);
    }

    #[Test]
    public function rehydrates_existing_assignment(): void
    {
        $definition = $this->makeDefinition();
        $unit = new TestUnit('unit-1');
        $payload = $this->makePayload(definition: $definition, unit: $unit);

        $this->repo->storeAssignment(new Assignment(
            experimentKey: 'test-experiment',
            unitType: 'test-user',
            unitKey: 'unit-1',
            variantKey: 'treatment',
            layer: null,
            assignedAt: new DateTimeImmutable(),
        ));

        $result = $this->step->handle($payload);

        self::assertTrue($result);
        self::assertTrue($payload->hasExistingAssignment);
        self::assertSame(TestVariant::treatment, $payload->resolvedVariant);
    }

    #[Test]
    public function treats_unknown_variant_key_as_no_assignment(): void
    {
        $definition = $this->makeDefinition();
        $unit = new TestUnit('unit-1');
        $payload = $this->makePayload(definition: $definition, unit: $unit);

        $this->repo->storeAssignment(new Assignment(
            experimentKey: 'test-experiment',
            unitType: 'test-user',
            unitKey: 'unit-1',
            variantKey: 'stale-variant-that-no-longer-exists',
            layer: null,
            assignedAt: new DateTimeImmutable(),
        ));

        $this->step->handle($payload);

        self::assertFalse($payload->hasExistingAssignment);
        self::assertNull($payload->resolvedVariant);
    }
}
