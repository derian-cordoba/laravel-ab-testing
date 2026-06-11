# Laravel A/B Testing

Standalone experimentation primitives for Laravel: typed experiments, deterministic bucketing, reusable metrics, Bayesian and frequentist analysis, and a package architecture that can grow into a full experimentation platform.

This package is intentionally not a thin feature-flag helper. It is designed around real experimentation concerns: stable assignment units, explicit control and traffic allocation, reusable metrics, sample-ratio mismatch detection, and normalized experiment definitions that can be consumed by both code-defined and runtime-defined workflows.

## Contents

- [Current status](#current-status)
- [Why this package](#why-this-package)
- [Installation](#installation)
- [Quick start](#quick-start)
- [Defining units](#defining-units)
- [Defining metrics](#defining-metrics)
- [Defining variants](#defining-variants)
- [Defining experiments](#defining-experiments)
- [Registering experiments](#registering-experiments)
- [Resolving variants](#resolving-variants)
- [Tracking metrics](#tracking-metrics)
- [Real-world request flow](#real-world-request-flow)
- [Discovery and caching](#discovery-and-caching)
- [Running statistical analysis](#running-statistical-analysis)
- [Feature flags](#feature-flags)
- [Architecture](#architecture)
- [Production integration](#production-integration)
- [Testing](#testing)
- [Current limitations](#current-limitations)
- [Contributing](#contributing)
- [License](#license)

## Current status

This repository already includes the foundational engine and several important runtime pieces:

- Attribute-based experiment definitions via `#[AsExperiment]`, `#[AsMetric]`, `#[AsUnit]`, and related attributes
- Typed variant enums with explicit control and weight declarations
- Deterministic bucketing with a SHA-256 strategy
- Assignment persistence behind repository contracts
- A resolution pipeline for eligibility, traffic checks, sticky assignment, and layer exclusion
- Event recording primitives for exposures and metrics
- Bayesian and frequentist analysis engines
- Sample-ratio mismatch detection
- Experiment registration from configuration and optional discovery

Some larger platform pieces are still intentionally not presented as finished:

- Production-grade persistence for assignments, events, and experiment state
- Dashboard UI
- Full feature-flag registry and runtime integration
- End-to-end database-backed operational workflows

The README below focuses on what exists today and shows how to use the package honestly in its current form.

## Why this package

Most Laravel A/B testing packages stop at "split users into two buckets and count conversions". This package is aiming at a stricter model:

- Experiments are typed PHP classes, not magic strings
- Variants are backed enums, not loose arrays
- Assignment is deterministic and sticky
- Assignment and exposure are different events
- Metrics are reusable definitions with explicit roles
- Analysis is unit-based, not raw-event-based
- Frequentist and Bayesian results can live side by side
- The engine consumes a normalized `ExperimentDefinition`, so code-defined and runtime-defined experiments can converge on the same core

## Installation

Install the package with Composer:

```bash
composer require derian-cordoba/laravel-ab-testing
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=ab-testing-config
```

The package supports Laravel package discovery, so you should not need to register the service provider manually.

### Requirements

- PHP `^8.4`
- Laravel components `illuminate/support ^13.0`
- `illuminate/console ^13.0`

## Quick start

The package revolves around four structural definitions:

1. A unit type that can be bucketed
2. A reusable metric
3. A backed enum of variants
4. An experiment class that ties them together

Here is a complete minimal example.

### 1. Define the assignment unit

```php
<?php

declare(strict_types=1);

namespace App\ABTesting;

use ABTests\Attributes\AsUnit;
use ABTests\Contracts\Bucketable;
use App\Models\User;

#[AsUnit(key: 'user')]
final readonly class ExperimentUser implements Bucketable
{
    public function __construct(private User $user)
    {
    }

    public function bucketingKey(): string
    {
        return "user:{$this->user->getKey()}";
    }

    public function attributes(): array
    {
        return [
            'plan' => $this->user->plan,
            'country' => $this->user->country,
        ];
    }
}
```

### 2. Define the metric

```php
<?php

declare(strict_types=1);

namespace App\ABTesting\Metrics;

use ABTests\Attributes\AsMetric;
use ABTests\Enums\Aggregate;
use ABTests\Enums\MetricType;
use ABTests\Metric;

#[AsMetric(
    key: 'checkout-conversion',
    type: MetricType::binary,
    event: 'checkout.completed',
    aggregate: Aggregate::uniqueUnits,
)]
final class CheckoutConversion extends Metric
{
}
```

### 3. Define the variants

```php
<?php

declare(strict_types=1);

namespace App\ABTesting\Variants;

use ABTests\Attributes\Control;
use ABTests\Attributes\Weight;
use ABTests\Concerns\IsVariant;
use ABTests\Contracts\Variant;

enum CheckoutButtonVariant: string implements Variant
{
    use IsVariant;

    #[Control]
    #[Weight(50)]
    case control = 'control';

    #[Weight(50)]
    case green = 'green';
}
```

### 4. Define the experiment

```php
<?php

declare(strict_types=1);

namespace App\ABTesting\Experiments;

use ABTests\Attributes\Analysis;
use ABTests\Attributes\AsExperiment;
use ABTests\Attributes\PrimaryMetric;
use ABTests\Enums\StatisticalEngine;
use ABTests\Experiment;
use ABTests\Values\Segment;
use App\ABTesting\ExperimentUser;
use App\ABTesting\Metrics\CheckoutConversion;
use App\ABTesting\Variants\CheckoutButtonVariant;

#[AsExperiment(
    key: 'checkout-button-color',
    unit: ExperimentUser::class,
    variants: CheckoutButtonVariant::class,
    name: 'Checkout button color',
    layer: 'checkout-ui',
)]
#[PrimaryMetric(CheckoutConversion::class)]
#[Analysis(
    engine: StatisticalEngine::both,
    confidenceLevel: 0.95,
    sequential: true,
)]
final class CheckoutButtonColor extends Experiment
{
    public function audience(): Segment
    {
        return Segment::where('plan', 'pro')->and('country', 'US');
    }
}
```

### 5. Register the experiment

Add it to `config/ab-testing.php`:

```php
'experiments' => [
    \App\ABTesting\Experiments\CheckoutButtonColor::class,
],
```

### 6. Resolve the variant

```php
use ABTests\Experiments;
use App\ABTesting\ExperimentUser;
use App\ABTesting\Experiments\CheckoutButtonColor;
use App\ABTesting\Variants\CheckoutButtonVariant;

$user = new ExperimentUser($request->user());

$variant = Experiments::for($user)->variant(CheckoutButtonColor::class);

if ($variant === null) {
    return view('checkout.default');
}

return match ($variant) {
    CheckoutButtonVariant::control => view('checkout.default'),
    CheckoutButtonVariant::green => view('checkout.green'),
};
```

### 7. Track the conversion

```php
use ABTests\Experiments;
use App\ABTesting\Metrics\CheckoutConversion;

Experiments::track(CheckoutConversion::class, for: $user);
```

## Defining units

Every experiment and metric event is anchored to a unit that implements `ABTests\Contracts\Bucketable`.

A unit must provide:

- `bucketingKey()`: the stable identifier used for deterministic assignment
- `attributes()`: a flat attribute array used for audience targeting

Use `#[AsUnit]` to give each unit type a stable storage key:

```php
#[AsUnit(key: 'tenant')]
final class TenantUnit implements Bucketable
{
    // ...
}
```

The `key` argument accepts either:

- a plain string
- a backed enum case
- a unit enum case, which resolves to its case name

That normalization follows the same semantics as Laravel's `enum_value()` helper.

## Defining metrics

Metrics are reusable definitions. An experiment does not describe how a conversion is measured; it references one or more metrics and assigns them roles.

Available metric attributes:

- `#[PrimaryMetric(...)]`
- `#[SecondaryMetric(...)]`
- `#[Guardrail(...)]`

Example:

```php
#[PrimaryMetric(CheckoutConversion::class)]
#[SecondaryMetric(RevenuePerVisitor::class)]
#[Guardrail(ErrorRate::class, maximumRegression: 0.01)]
```

The `#[AsMetric]` attribute describes the metric itself:

```php
#[AsMetric(
    key: 'revenue-per-visitor',
    type: MetricType::continuous,
    event: 'checkout.completed',
    aggregate: Aggregate::sum,
    valueFromProperty: 'revenue',
    attributionWindow: '7 days',
)]
final class RevenuePerVisitor extends Metric
{
    public function valueOf(array $properties): float
    {
        return (float) ($properties['revenue'] ?? 0.0);
    }
}
```

### Metric roles

- Primary metric: drives the ship or do-not-ship decision
- Secondary metric: useful for supporting interpretation
- Guardrail metric: must not regress beyond the allowed threshold

### Raw metric keys are also supported

The runtime tracking layer accepts either:

- a metric class-string, such as `CheckoutConversion::class`
- a raw metric key, such as `'checkout-conversion'`

That matters because the core engine is designed to support both code-defined and runtime-defined experiments.

## Defining variants

Variants must be backed enums that implement `ABTests\Contracts\Variant`. The easiest way to satisfy the contract is to use the `ABTests\Concerns\IsVariant` trait.

Rules enforced by the package:

- each variant must have a `#[Weight(...)]`
- weights must sum to `100`
- exactly one variant must carry `#[Control]`

Example with three arms:

```php
enum PricingPageVariant: string implements Variant
{
    use IsVariant;

    #[Control]
    #[Weight(34)]
    case control = 'control';

    #[Weight(33)]
    case headlineA = 'headline_a';

    #[Weight(33)]
    case headlineB = 'headline_b';
}
```

The package preserves the original enum case on resolution, so downstream application code can use `match` exhaustively and stay type-safe.

## Defining experiments

Use `#[AsExperiment]` on a class extending `ABTests\Experiment`.

```php
#[AsExperiment(
    key: 'pricing-page-headline',
    unit: ExperimentUser::class,
    variants: PricingPageVariant::class,
    name: 'Pricing page headline',
    layer: 'pricing-page',
)]
```

### Experiment structure

The attribute captures the structural parts of an experiment:

- stable experiment key
- assignment unit type
- variant enum
- optional human-readable name
- optional mutual-exclusion layer

These are treated as code-owned structure. In the intended architecture, operational state such as running, paused, and traffic percentage belongs to persistence and dashboard workflows, not the class definition itself.

### Audience targeting

Override `audience()` when the experiment should only apply to a segment:

```php
use ABTests\Experiment;
use ABTests\Values\Segment;

final class PricingPageHeadline extends Experiment
{
    public function audience(): Segment
    {
        return Segment::where('country', 'US')
            ->and('plan', 'pro');
    }
}
```

The current `Segment` API supports conjunctions of criteria and operators such as:

- `equals`
- `notEquals`
- `in`
- `notIn`
- `greaterThan`
- `lessThan`

## Registering experiments

The package boots an `ExperimentRegistry` from configuration.

In `config/ab-testing.php`:

```php
return [
    'experiments' => [
        \App\ABTesting\Experiments\CheckoutButtonColor::class,
        \App\ABTesting\Experiments\PricingPageHeadline::class,
    ],

    'discovery' => [
        'enabled' => false,
        'paths' => [
            // app_path('ABTesting/Experiments'),
        ],
    ],
];
```

If an experiment cannot be read at boot, the service provider logs the failure instead of crashing the whole app.

## Resolving variants

The main entry point is `ABTests\Experiments`.

```php
$variant = Experiments::for($unit)->variant(CheckoutButtonColor::class);
```

Resolution goes through a pipeline with the following responsibilities:

- check whether the experiment is active
- verify segment eligibility
- verify traffic allocation
- load any existing sticky assignment
- enforce layer exclusion
- compute a deterministic bucket when needed
- persist the assignment

When a variant is successfully resolved, the package records an exposure event through the configured `EventSink`.

### Null return values

`variant()` returns `null` when the unit should not receive the experiment at all. Typical reasons include:

- the experiment is not active
- the unit does not match the audience segment
- the unit falls outside the active traffic percentage
- the unit is excluded by a layer conflict

That is intentional. "Not eligible" is not the same thing as "assigned to control".

## Tracking metrics

Track a metric for a unit with either the facade or the per-unit resolver:

```php
Experiments::track(CheckoutConversion::class, for: $unit);

Experiments::for($unit)->track(CheckoutConversion::class);
```

You can also pass a raw metric key:

```php
Experiments::for($unit)->track('checkout-conversion');
```

For continuous metrics:

```php
Experiments::for($unit)->track('revenue-per-visitor', value: 149.99);
```

Tracking behavior:

- the metric key is normalized from the metric class when possible
- the resolver iterates over registered experiment definitions
- only experiments with a current assignment for that unit receive a metric event
- the event is recorded with a unique idempotency key

## Real-world request flow

Below is a realistic end-to-end checkout flow showing where variant resolution and metric tracking usually belong in an application.

### Controller example

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use ABTests\Experiments;
use App\ABTesting\ExperimentUser;
use App\ABTesting\Experiments\CheckoutButtonColor;
use App\ABTesting\Metrics\CheckoutConversion;
use App\ABTesting\Variants\CheckoutButtonVariant;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class CheckoutController
{
    public function show(Request $request): View
    {
        $user = new ExperimentUser($request->user());

        $variant = Experiments::for($user)->variant(CheckoutButtonColor::class);

        return match ($variant) {
            CheckoutButtonVariant::green => view('checkout.green'),
            CheckoutButtonVariant::control,
            null => view('checkout.default'),
        };
    }

    public function complete(Request $request): \Illuminate\Http\RedirectResponse
    {
        $user = new ExperimentUser($request->user());

        // Your real checkout logic goes here.

        Experiments::track(CheckoutConversion::class, for: $user);

        return redirect()->route('checkout.success');
    }
}
```

### Practical guidance

- Resolve the variant at the point where the user actually experiences the change, not earlier in the request chain for convenience.
- Track the metric at the point where the outcome actually happens, not when you merely intend it to happen.
- Rebuild the same unit shape consistently across requests so sticky assignment remains meaningful.
- Treat `null` as "this user is not in the experiment", not as "fallback control".
- Keep experiment keys, metric keys, and unit keys stable over time. Changing them creates a new experiment from the engine's point of view.

### A good mental model

One safe way to think about the package is:

- `variant()` is for exposure
- `track()` is for outcomes

If those two calls happen in the correct places, the rest of the package architecture stays coherent.

## Discovery and caching

The package includes an `ab:cache` Artisan command:

```bash
php artisan ab:cache
```

When discovery is enabled in config, the command scans configured paths for PHP classes, extracts class names without executing the files, reads `#[AsExperiment]` definitions, and registers them in the runtime registry.

Example config:

```php
'discovery' => [
    'enabled' => true,
    'paths' => [
        app_path('ABTesting/Experiments'),
    ],
],
```

This is useful when you want explicit deployment-time registration without manually maintaining a long config list.

## Running statistical analysis

The analysis layer is already present as a framework-agnostic service. It expects pre-aggregated unit-level summaries rather than raw events.

```php
use ABTests\Statistics\AnalysisService;
use ABTests\Values\GenericVariant;
use ABTests\Values\MetricSummary;

$control = new MetricSummary(
    variant: new GenericVariant('control', 50, true),
    countOfUnits: 1000,
    sumOfValues: 120,
    sumOfSquaredValues: 120,
    conversions: 120,
);

$treatment = new MetricSummary(
    variant: new GenericVariant('green', 50),
    countOfUnits: 1000,
    sumOfValues: 145,
    sumOfSquaredValues: 145,
    conversions: 145,
);

$result = app(AnalysisService::class)->analyse(
    definition: $definition,
    control: $control,
    treatment: $treatment,
    allSummaries: [$control, $treatment],
);

$result->verdict;      // ship | doNotShip | inconclusive
$result->frequentist;  // AnalysisResult|null
$result->bayesian;     // AnalysisResult|null
$result->srm;          // SampleRatioMismatchResult
```

### What the analysis layer does

- runs the configured engine: frequentist, Bayesian, or both
- performs sample-ratio mismatch detection across all summaries
- returns a `VerdictResult` with both raw engine outputs and the final verdict

### Statistical defaults

By default, experiments use:

- `StatisticalEngine::both`
- `0.95` confidence
- sequential inference enabled

These defaults come from `AnalysisConfiguration::default()`.

## Feature flags

The package already contains a `FeatureFlag` base class and `#[AsFeatureFlag]` attribute:

```php
use ABTests\Attributes\AsFeatureFlag;
use ABTests\FeatureFlag;
use ABTests\Values\Context;
use App\ABTesting\ExperimentUser;

#[AsFeatureFlag(
    key: 'new-billing-page',
    unit: ExperimentUser::class,
    defaultValue: false,
)]
final class NewBillingPageFlag extends FeatureFlag
{
    public function resolve(Context $context): mixed
    {
        if ($context->attribute('plan') === 'enterprise') {
            return true;
        }

        return $this->rollout(20, $context);
    }
}
```

At the moment, the flag primitives are present, but the full flag registry and application-facing runtime integration are not yet documented as finished in this package. Treat this as a foundation for upcoming work rather than a fully polished surface.

## Architecture

The package follows a layered design:

- Domain-like definitions and value objects
- Resolution and analysis services
- Infrastructure behind contracts
- Laravel service provider integration

Key contracts and abstractions:

- `BucketingStrategy`
- `AssignmentRepository`
- `ExperimentStateRepository`
- `EventSink`
- `AnalysisEngine`

Default bindings currently include:

- `Sha256BucketingStrategy`
- `InMemoryAssignmentRepository`
- `AlwaysRunningExperimentStateRepository`
- `NullEventSink`

That means the package is easy to experiment with immediately, while still leaving the important persistence seams replaceable.

## Production integration

Out of the box, the package uses development-friendly defaults:

- `InMemoryAssignmentRepository`
- `AlwaysRunningExperimentStateRepository`
- `NullEventSink`

Those are useful for tests, examples, and early integration, but they are not the shape you want in production.

### What to replace

For a serious deployment, you should provide concrete implementations for at least these contracts:

- `ABTests\Contracts\AssignmentRepository`
- `ABTests\Contracts\ExperimentStateRepository`
- `ABTests\Contracts\EventSink`

### Recommended responsibilities

`AssignmentRepository`

- Persist sticky assignments keyed by experiment key, unit type, and unit key
- Make writes idempotent
- Support layer lookups so mutual exclusion works across running experiments

`ExperimentStateRepository`

- Return the operational state for an experiment
- Control whether an experiment is considered live
- Surface traffic percentage, pause state, and kill-switch-like behavior through `ExperimentState`

`EventSink`

- Record exposure events
- Record metric and conversion events
- Prefer append-only writes
- Make batching easy, even if the interface is used one event at a time

### Rebinding the contracts

In your application service provider:

```php
<?php

declare(strict_types=1);

namespace App\Providers;

use ABTests\Contracts\AssignmentRepository;
use ABTests\Contracts\EventSink;
use ABTests\Contracts\ExperimentStateRepository;
use App\ABTesting\Infrastructure\DatabaseAssignmentRepository;
use App\ABTesting\Infrastructure\DatabaseExperimentStateRepository;
use App\ABTesting\Infrastructure\DatabaseEventSink;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AssignmentRepository::class, DatabaseAssignmentRepository::class);
        $this->app->singleton(ExperimentStateRepository::class, DatabaseExperimentStateRepository::class);
        $this->app->singleton(EventSink::class, DatabaseEventSink::class);
    }
}
```

### Suggested database model

The package does not yet ship its own full production schema, but the intended shape is close to:

- `assignments`
  stores one sticky assignment per experiment, unit type, and unit key
- `events`
  append-only exposure and metric events with idempotency keys
- `experiments`
  operational state such as running, paused, traffic percentage, and timestamps

### Assignment repository example

```php
<?php

declare(strict_types=1);

namespace App\ABTesting\Infrastructure;

use ABTests\Contracts\AssignmentRepository;
use ABTests\Values\Assignment;

final class DatabaseAssignmentRepository implements AssignmentRepository
{
    public function findAssignment(
        string $experimentKey,
        string $unitType,
        string $unitKey,
    ): ?Assignment {
        // Load and hydrate from your persistence layer.
    }

    public function storeAssignment(Assignment $assignment): void
    {
        // Use an insert that does not overwrite an existing assignment.
    }

    public function findAssignmentByLayer(
        string $layer,
        string $unitType,
        string $unitKey,
    ): ?Assignment {
        // Resolve the first live assignment in the given layer for this unit.
    }
}
```

### Event sink example

```php
<?php

declare(strict_types=1);

namespace App\ABTesting\Infrastructure;

use ABTests\Contracts\EventSink;
use ABTests\Values\RecordedEvent;

final class DatabaseEventSink implements EventSink
{
    public function record(RecordedEvent $event): void
    {
        $this->recordBatch([$event]);
    }

    public function recordBatch(iterable $events): void
    {
        foreach ($events as $event) {
            // Insert append-only rows, ideally with an idempotency unique index.
        }
    }
}
```

### Production checklist

- Replace the default repositories and sink bindings
- Make assignment writes idempotent
- Make event writes append-only
- Add a unique constraint on event idempotency keys
- Keep experiment operational state outside the PHP class definition
- Ensure your unit attributes are reproducible across requests
- Monitor failed registration logs during deploys
- Run `php artisan ab:cache` as part of deployment if you use discovery

### What not to do

- Do not store experiment structure only in the database if your current integration is using code-defined experiments.
- Do not change variant keys or experiment keys mid-flight.
- Do not count raw events directly as if they were unit-level observations for statistical decisions.
- Do not treat control assignment and ineligibility as the same thing.

## Testing

Run the full test suite:

```bash
composer test
```

Or directly with PHPUnit:

```bash
vendor/bin/phpunit
```

Static analysis:

```bash
composer analyse
```

The repository also includes a GitHub Actions workflow for tests.

## Current limitations

This package is not pretending to be more finished than it is. Before adopting it in production, keep these points in mind:

- The default repositories are in-memory or always-on placeholders
- Event persistence is behind a contract, but a production event store is not yet shipped here
- Dashboard workflows are planned, not complete
- Feature-flag definition primitives exist, but the end-user flag runtime is not yet the main surface
- The architecture is designed for runtime-defined experiments too, but the most complete experience today is the code-defined attribute flow

If you want to evaluate the package today, the strongest implemented path is:

- code-defined experiments
- deterministic resolution
- metric tracking contracts
- analysis primitives
- custom infrastructure implementations in your app

## Contributing

Contributions are welcome, especially around:

- production persistence implementations
- documentation examples
- feature-flag runtime integration
- dashboard-facing read models
- additional tests around the resolution and analysis layers

If you open a pull request, keep the current package direction in mind:

- strong typing over stringly APIs
- stable experiment, metric, and unit keys
- explicit structure in code
- replaceable infrastructure

## License

This package is open-sourced under the MIT license.
