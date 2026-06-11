<?php

declare(strict_types=1);

namespace ABTests\Tests\Fixtures;

use ABTests\Attributes\AsFeatureFlag;
use ABTests\FeatureFlag;
use ABTests\Values\Context;

/**
 * Minimal feature flag fixture used by AttributeReader and flag-resolution tests.
 * Resolves to true whenever the resolution pipeline calls resolve().
 */
#[AsFeatureFlag(
    key: 'test-flag',
    unit: TestUnitType::class,
    defaultValue: false,
)]
final class TestFeatureFlag extends FeatureFlag
{
    public function resolve(Context $context): bool
    {
        return true;
    }
}
