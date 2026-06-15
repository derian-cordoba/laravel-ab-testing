<?php

declare(strict_types=1);

namespace ABTests\Tests\Unit\Strategies;

use ABTests\Infrastructure\Bucketing\Sha256BucketingStrategy;
use ABTests\Tests\Fixtures\TestUnit;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class Sha256BucketingStrategyTest extends TestCase
{
    private Sha256BucketingStrategy $strategy;

    protected function setUp(): void
    {
        $this->strategy = new Sha256BucketingStrategy();
    }

    #[Test]
    public function same_salt_and_unit_always_return_same_position(): void
    {
        $unit = new TestUnit('user-42');
        $salt = 'checkout-button-color';

        $pos1 = $this->strategy->position($salt, $unit);
        $pos2 = $this->strategy->position($salt, $unit);

        self::assertSame($pos1, $pos2);
    }

    #[Test]
    public function position_is_in_half_open_zero_one_interval(): void
    {
        $unit = new TestUnit('user-1');

        $pos = $this->strategy->position('some-experiment', $unit);

        self::assertGreaterThanOrEqual(0.0, $pos);
        self::assertLessThan(1.0, $pos);
    }

    #[Test]
    public function different_salts_yield_different_positions_for_same_unit(): void
    {
        $unit = new TestUnit('user-1');

        $pos1 = $this->strategy->position('experiment-a', $unit);
        $pos2 = $this->strategy->position('experiment-b', $unit);

        // With SHA-256 it is astronomically unlikely these collide
        self::assertNotSame($pos1, $pos2);
    }

    #[Test]
    public function different_units_yield_different_positions_for_same_salt(): void
    {
        $salt = 'my-experiment';

        $pos1 = $this->strategy->position($salt, new TestUnit('user-1'));
        $pos2 = $this->strategy->position($salt, new TestUnit('user-2'));

        self::assertNotSame($pos1, $pos2);
    }

    #[Test]
    public function distribution_is_roughly_uniform(): void
    {
        $buckets = array_fill(0, 10, 0);

        for ($i = 0; $i < 1000; $i++) {
            $pos = $this->strategy->position('dist-test', new TestUnit("user-$i"));
            $bucket = (int) floor($pos * 10);
            $buckets[$bucket]++;
        }

        // Each bucket should contain roughly 100 units (±40 with reasonable confidence).
        foreach ($buckets as $index => $count) {
            self::assertGreaterThan(60, $count, "Bucket $index is under-represented");
            self::assertLessThan(140, $count, "Bucket $index is over-represented");
        }
    }
}
