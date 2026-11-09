<?php

declare(strict_types=1);

namespace ABTests;

/**
 * @deprecated Use ABTests\Domain\FeatureFlag\FeatureFlag instead.
 *
 * Backward-compatible shim — kept so existing user-defined subclasses that
 * extend ABTests\FeatureFlag continue to work without changes. The canonical
 * class now lives in the FeatureFlag bounded context.
 */
abstract class FeatureFlag extends \ABTests\Domain\FeatureFlag\FeatureFlag {}
