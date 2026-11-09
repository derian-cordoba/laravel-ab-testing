<?php

declare(strict_types=1);

namespace ABTests;

/**
 * @deprecated Use ABTests\Domain\Experiment\Experiment instead.
 *
 * Backward-compatible shim — kept so existing user-defined subclasses that
 * extend ABTests\Experiment continue to work without changes. The canonical
 * class now lives in the Experiment bounded context.
 */
abstract class Experiment extends \ABTests\Domain\Experiment\Experiment {}
