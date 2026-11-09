<?php

declare(strict_types=1);

namespace ABTests;

/**
 * @deprecated Use ABTests\Domain\Analysis\Metric instead.
 *
 * Backward-compatible shim — kept so existing user-defined subclasses that
 * extend ABTests\Metric continue to work without changes. The canonical
 * class now lives in the Analysis bounded context.
 */
abstract class Metric extends \ABTests\Domain\Analysis\Metric {}
