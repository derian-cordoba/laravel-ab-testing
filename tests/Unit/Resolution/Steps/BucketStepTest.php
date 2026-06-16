<?php

declare(strict_types=1);

namespace ABTests\Tests\Unit\Resolution\Steps;

use ABTests\Application\Resolution\Steps\BucketStep;
use ABTests\Tests\Fixtures\TestVariant;
use ABTests\Tests\Support\MakesPayload;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BucketStepTest extends TestCase
{
    use MakesPayload;

    #[Test]
    public function maps_position_to_control_variant(): void
    {
        $payload = $this->makePayload(bucketPosition: 0.1);

        (new BucketStep())->handle($payload);

        self::assertSame(TestVariant::control, $payload->resolvedVariant);
    }

    #[Test]
    public function maps_position_to_treatment_variant(): void
    {
        $payload = $this->makePayload(bucketPosition: 0.75);

        (new BucketStep())->handle($payload);

        self::assertSame(TestVariant::treatment, $payload->resolvedVariant);
    }

    #[Test]
    public function skips_bucketing_when_existing_assignment_present(): void
    {
        $payload = $this->makePayload(bucketPosition: 0.1);
        $payload->hasExistingAssignment = true;
        $payload->resolvedVariant = TestVariant::treatment; // pre-set by rehydration

        (new BucketStep())->handle($payload);

        // Existing assignment preserved — treatment is not overwritten to control
        self::assertSame(TestVariant::treatment, $payload->resolvedVariant);
    }

    #[Test]
    public function always_returns_true(): void
    {
        $payload = $this->makePayload();
        self::assertTrue((new BucketStep())->handle($payload));
    }
}
