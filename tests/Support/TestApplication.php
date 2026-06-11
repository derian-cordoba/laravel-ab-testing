<?php

declare(strict_types=1);

namespace ABTests\Tests\Support;

use Illuminate\Container\Container;

/**
 * A thin wrapper around the Illuminate Container that adds `environment()` so
 * that code paths using `app()->environment()` work inside the test suite
 * without requiring illuminate/foundation.
 */
final class TestApplication extends Container
{
    public function environment(): string
    {
        return 'testing';
    }
}
