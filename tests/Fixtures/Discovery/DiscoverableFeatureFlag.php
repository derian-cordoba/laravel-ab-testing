<?php

declare(strict_types=1);

namespace ABTests\Tests\Fixtures\Discovery;

use ABTests\FeatureFlag;
use ABTests\Values\Context;

/**
 * Minimal FeatureFlag subclass for ClassDiscovery and bootDiscovery routing tests.
 * No #[AsFeatureFlag] attribute is required — ClassDiscovery only checks the base class.
 */
final class DiscoverableFeatureFlag extends FeatureFlag
{
    public function resolve(Context $context): bool
    {
        return false;
    }
}
