<?php

declare(strict_types=1);

namespace ABTests\Enums;

/**
 * Deployment environment a definition is being resolved in. Lets the same
 * flag or experiment behave differently outside production.
 */
enum Environment: string
{
    case local = 'local';
    case staging = 'staging';
    case production = 'production';
}
