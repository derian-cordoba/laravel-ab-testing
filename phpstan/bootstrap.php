<?php

declare(strict_types=1);

/**
 * PHPStan bootstrap for a standalone Laravel package.
 *
 * Larastan's bootstrap.php tries to boot a full Laravel application to resolve
 * LARAVEL_VERSION, but this repository has no bootstrap/app.php. Define the
 * constant here so Larastan's stub-file extension can run without a full app.
 */
if (! defined('LARAVEL_VERSION')) {
    define('LARAVEL_VERSION', '13.0.0');
}
