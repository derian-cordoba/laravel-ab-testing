<?php

declare(strict_types=1);

namespace ABTests\Tests\Fixtures;

use ABTests\Attributes\AsFeatureFlag;
use ABTests\FeatureFlag;
use ABTests\Values\Context;

/**
 * Feature flag whose unit class is missing the #[AsUnit] attribute.
 * Used to verify AttributeReader throws when the unit type is undeclared.
 */
#[AsFeatureFlag(
    key: 'bad-unit-flag',
    unit: UndeclaredUnitType::class,
    defaultValue: false,
)]
final class FeatureFlagWithMissingUnitAttribute extends FeatureFlag
{
    public function resolve(Context $context): bool
    {
        return false;
    }
}
