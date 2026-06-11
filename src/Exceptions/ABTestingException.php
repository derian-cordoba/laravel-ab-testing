<?php

declare(strict_types=1);

namespace ABTests\Exceptions;

use Throwable;

/**
 * Marker implemented by every exception the framework throws, so consumers
 * can catch all package failures with a single type.
 */
interface ABTestingException extends Throwable
{
}
