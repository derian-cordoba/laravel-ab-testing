<?php

declare(strict_types=1);

namespace ABTests\Tests\Unit\Resolution\Steps;

use ABTests\Infrastructure\InMemoryAssignmentRepository;
use ABTests\Resolution\Steps\PersistAssignmentStep;
use ABTests\Tests\Fixtures\TestUnit;
use ABTests\Tests\Fixtures\TestVariant;
use ABTests\Tests\Support\MakesPayload;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PersistAssignmentStepTest extends TestCase
{
    use MakesPayload;

    private InMemoryAssignmentRepository $repo;
    private PersistAssignmentStep $step;

    protected function setUp(): void
    {
        $this->repo = new InMemoryAssignmentRepository();
        $this->step = new PersistAssignmentStep($this->repo);
    }

    #[Test]
    public function stores_resolved_variant(): void
    {
        $definition = $this->makeDefinition();
        $unit = new TestUnit('unit-1');
        $payload = $this->makePayload(definition: $definition, unit: $unit);
        $payload->resolvedVariant = TestVariant::treatment;

        $this->step->handle($payload);

        $assignment = $this->repo->findAssignment('test-experiment', 'test-user', 'unit-1');
        self::assertNotNull($assignment);
        self::assertSame('treatment', $assignment->variantKey);
    }

    #[Test]
    public function skips_when_existing_assignment_flag_is_set(): void
    {
        $definition = $this->makeDefinition();
        $unit = new TestUnit('unit-1');
        $payload = $this->makePayload(definition: $definition, unit: $unit);
        $payload->resolvedVariant = TestVariant::treatment;
        $payload->hasExistingAssignment = true;

        $this->step->handle($payload);

        self::assertNull($this->repo->findAssignment('test-experiment', 'test-user', 'unit-1'));
    }

    #[Test]
    public function skips_when_no_resolved_variant(): void
    {
        $definition = $this->makeDefinition();
        $unit = new TestUnit('unit-1');
        $payload = $this->makePayload(definition: $definition, unit: $unit);
        // resolvedVariant remains null

        $this->step->handle($payload);

        self::assertNull($this->repo->findAssignment('test-experiment', 'test-user', 'unit-1'));
    }

    #[Test]
    public function stores_layer_on_assignment_when_definition_has_layer(): void
    {
        $definition = $this->makeDefinition(layer: 'checkout');
        $unit = new TestUnit('unit-1');
        $payload = $this->makePayload(definition: $definition, unit: $unit);
        $payload->resolvedVariant = TestVariant::control;

        $this->step->handle($payload);

        $layerAssignment = $this->repo->findAssignmentByLayer('checkout', 'test-user', 'unit-1');
        self::assertNotNull($layerAssignment);
        self::assertSame('checkout', $layerAssignment->layer);
    }
}
