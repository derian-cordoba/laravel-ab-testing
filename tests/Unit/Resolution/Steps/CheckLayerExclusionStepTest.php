<?php

declare(strict_types=1);

namespace ABTests\Tests\Unit\Resolution\Steps;

use ABTests\Infrastructure\InMemoryAssignmentRepository;
use ABTests\Application\Resolution\Steps\CheckLayerExclusionStep;
use ABTests\Tests\Fixtures\TestUnit;
use ABTests\Tests\Support\MakesPayload;
use ABTests\Values\Assignment;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CheckLayerExclusionStepTest extends TestCase
{
    use MakesPayload;

    private InMemoryAssignmentRepository $repo;
    private CheckLayerExclusionStep $step;

    protected function setUp(): void
    {
        $this->repo = new InMemoryAssignmentRepository();
        $this->step = new CheckLayerExclusionStep($this->repo);
    }

    #[Test]
    public function returns_true_when_no_layer_defined(): void
    {
        $definition = $this->makeDefinition(layer: null);
        $payload = $this->makePayload(definition: $definition);

        self::assertTrue($this->step->handle($payload));
    }

    #[Test]
    public function returns_true_when_no_other_experiment_in_layer(): void
    {
        $definition = $this->makeDefinition(key: 'exp-a', layer: 'checkout');
        $unit = new TestUnit('unit-1');
        $payload = $this->makePayload(definition: $definition, unit: $unit);

        self::assertTrue($this->step->handle($payload));
    }

    #[Test]
    public function returns_false_when_unit_assigned_to_different_experiment_in_same_layer(): void
    {
        $this->repo->storeAssignment(new Assignment(
            experimentKey: 'exp-other',
            unitType: 'test-user',
            unitKey: 'unit-1',
            variantKey: 'control',
            layer: 'checkout',
            assignedAt: new DateTimeImmutable(),
        ));

        $definition = $this->makeDefinition(key: 'exp-a', layer: 'checkout');
        $unit = new TestUnit('unit-1');
        $payload = $this->makePayload(definition: $definition, unit: $unit);

        self::assertFalse($this->step->handle($payload));
    }

    #[Test]
    public function returns_true_when_existing_layer_assignment_is_for_same_experiment(): void
    {
        // Same experiment — no mutual exclusion, unit re-enters fine.
        $this->repo->storeAssignment(new Assignment(
            experimentKey: 'exp-a',
            unitType: 'test-user',
            unitKey: 'unit-1',
            variantKey: 'control',
            layer: 'checkout',
            assignedAt: new DateTimeImmutable(),
        ));

        $definition = $this->makeDefinition(key: 'exp-a', layer: 'checkout');
        $unit = new TestUnit('unit-1');
        $payload = $this->makePayload(definition: $definition, unit: $unit);

        self::assertTrue($this->step->handle($payload));
    }

    #[Test]
    public function skips_check_when_existing_assignment_already_set(): void
    {
        // Simulate that LoadExistingAssignmentStep already handled this unit.
        $this->repo->storeAssignment(new Assignment(
            experimentKey: 'exp-other',
            unitType: 'test-user',
            unitKey: 'unit-1',
            variantKey: 'control',
            layer: 'checkout',
            assignedAt: new DateTimeImmutable(),
        ));

        $definition = $this->makeDefinition(key: 'exp-a', layer: 'checkout');
        $unit = new TestUnit('unit-1');
        $payload = $this->makePayload(definition: $definition, unit: $unit);
        $payload->hasExistingAssignment = true;

        self::assertTrue($this->step->handle($payload));
    }
}
