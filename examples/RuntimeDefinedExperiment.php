<?php

declare(strict_types=1);

/**
 * Front-end 2: runtime-defined experiment. This is the "general" path: an
 * experiment assembled from plain data (as a dashboard would, from database
 * rows) with no attributes and no enum. It builds the very same
 * ExperimentDefinition the attribute reader produces, so the resolver, the
 * statistics, and the dashboard treat it identically.
 */

use ABTests\Definitions\ExperimentDefinition;
use ABTests\Definitions\MetricBinding;
use ABTests\Enums\MetricRole;
use ABTests\Values\Allocation;
use ABTests\Values\AnalysisConfiguration;
use ABTests\Values\GenericUnit;
use ABTests\Values\GenericVariant;
use ABTests\Values\Segment;

// Variants come from data, not a hard-coded enum.
$allocation = Allocation::fromVariants([
    new GenericVariant(key: 'control', weight: 50, isControl: true),
    new GenericVariant(key: 'green', weight: 25),
    new GenericVariant(key: 'blue', weight: 25),
]);

$definition = new ExperimentDefinition(
    key: 'checkout-button-color',
    unitType: 'tenant',
    allocation: $allocation,
    analysis: AnalysisConfiguration::default(),
    audience: Segment::where('plan', 'pro'),
    metrics: [
        new MetricBinding('checkout-conversion', MetricRole::primary),
        new MetricBinding('error-rate', MetricRole::guardrail, maximumRegression: 0.005),
    ],
    name: 'Checkout button colour',
    layer: 'checkout',
);

// A subject built from a plain id + attributes (e.g. a guest, or a hydrated row).
$unit = new GenericUnit(key: 'tenant:42', attributes: ['plan' => 'pro', 'country' => 'US']);

// Same downstream API as the typed example — the engine never knows the source.
// $variant = Experiments::for($unit)->variantOf($definition);
// Experiments::track('checkout-conversion', for: $unit);
