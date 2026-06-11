# CLAUDE.md — Laravel A/B Testing Framework

Complete context + reference for `derian-cordoba/laravel-ab-testing`. This file
contains the plan, the hard constraints, the architecture, **and the full
definitions of every class scaffolded so far**. Treat **Constraints** and
**Naming conventions** as hard rules. Items under **Open questions** are
unresolved — surface them, do not silently decide them.

> Status: the foundational layer (enums, contracts, attributes, value objects,
> normalized definitions, base classes) is built and embedded below in §8–§9.
> The resolver, registry, strategy implementations, storage, and dashboard are
> **not yet built**.

---

## 1. Mission

Build a **standalone, general-purpose A/B testing and experimentation framework
for Laravel** — feature flags, experiments, metrics, a statistics engine, and a
results dashboard, shipped as one Composer package. It must be a *complete
experimentation platform*, not a thin helper over feature flags.

Reference point we are deliberately surpassing — `jamesblackwell/laravel-ab-testing`,
whose limitations define what we avoid:

- Public API is global helper functions with magic-string experiment names.
- Storage is counter columns — no re-analysis, no post-hoc segmentation, no
  Bayesian posterior, races on increment. **Aggregates must be a derived cache,
  never the source of truth.**
- Assignment is not intrinsically deterministic.
- Variants are stringly-typed, max two.
- Definitions live as service-provider closures (not discoverable/testable).
- Frequentist p-value only; Pennant-coupled; no segments, no multi-level units,
  no guardrails.

---

## 2. Locked decisions (do not revisit without flagging)

1. **Standalone.** Not built on Laravel Pennant. We own bucketing, storage,
   statistics, lifecycle, dashboard.
2. **Both statistics engines.** Frequentist *and* Bayesian, side by side.
3. **Multi-level assignment units.** user / tenant / session / device / custom.
   An experiment declares which level it buckets on.
4. **Full v1 scope.** Experiments + feature flags + dashboard ship together.
5. **PHP 8.4**, design-pattern-driven, layered architecture.
6. **Two front-ends over one engine.** Attribute/enum definitions and
   runtime/database-defined experiments both normalize to one internal
   representation (`ExperimentDefinition`). The package is general, NOT
   attribute-only.
7. **Storage: PostgreSQL** as the single store for now, behind a `Repository`
   contract for a future analytical-store swap.
8. **Structure in code, operational state + measurement in the database.**
   Structure (variants, unit, metrics, allocation, segments) is version-
   controlled and read-only at runtime. Operational state (status, traffic, kill
   switch, overrides) and all measurement live in the DB and are driven from the
   dashboard. The dashboard is a control room + results console, not a builder
   for code-defined experiments.

### Package coordinates

- Name: `derian-cordoba/laravel-ab-testing`
- Root namespace: `ABTests\` (PSR-4, `src/`)
- Requires: `php: ^8.4`, `illuminate/support: ^12.0 || ^13.0`
- License: MIT

---

## 3. Naming conventions (hard rule)

- **No acronyms or abbreviations in identifiers — use full words/phrases.**
  Applied: `traffic_percentage` (not `traffic_pct`), `sum_of_squared_values`
  (not `sum_sq_value`), `count_of_units` (not `units`), `maximumRegression`
  (not `maxRegression`), `confidenceLevel` (not `confidence`).
- Only accepted short form: `id` as a database primary key (Laravel convention).
- Experiment/flag/metric keys are kebab-case (`checkout-button-color`).
- Every PHP file: `declare(strict_types=1)`; `final` by default; `readonly`
  value objects; PSR-4 file/class name parity.
- Collision rule: config **attributes** are `#[AsExperiment]` / `#[AsFeatureFlag]`
  / `#[AsMetric]` / `#[AsUnit]`; the **base classes** consumers extend are
  `Experiment` / `FeatureFlag` / `Metric`. They cannot share a name.

---

## 4. Architecture

Layered / hexagonal. Domain core is framework-agnostic; Laravel is an adapter.

- **Domain** — entities, value objects, rules. No Laravel dependency where
  avoidable.
- **Application** — services, commands, the resolution pipeline.
- **Infrastructure** — Eloquent repositories, event sinks, cache, bindings.
- **Presentation** — dashboard, Blade directives, attribute middleware.

### Two front-ends, one engine

Both paths produce the same `ABTests\Definitions\ExperimentDefinition`, the
source-agnostic contract the resolver, statistics, and dashboard consume.

- **Code-defined (typed):** `#[AsExperiment]` class + a backed enum of variants
  (`IsVariant` trait). Maximum compile-time safety.
- **Runtime-defined (general):** experiments assembled from plain data
  (`GenericVariant`, `GenericUnit`, `Segment`, `MetricBinding`) — no attributes,
  no enum — as a dashboard/database loader would. Builds the identical
  `ExperimentDefinition`.

### Design patterns (in use / planned)

- **Registry + auto-discovery** — scan dirs, reflect attributes, cache a manifest
  (`php artisan ab:cache`).
- **Pipeline** for resolution: eligibility → segment match → holdout →
  deterministic bucketing → sticky persistence → exposure.
- **Strategy** for three seams: `BucketingStrategy`, `AnalysisEngine`,
  `EventSink`.
- **Repository** behind the event/analysis store.
- **Specification** for `Segment`.
- **State machine** for lifecycle (`ExperimentStatus`).
- **Command bus + domain events** for audited dashboard actions and reactions
  (`GuardrailBreached → AutoPause`).
- **Value Objects / DTOs** — immutable `readonly`; computed figures via PHP 8.4
  property hooks.

---

## 5. Core business rules

### Feature flags vs experiments
Distinct concepts sharing an assignment engine. A **flag** controls exposure
(release management): resolves to a value, fail-safe default, kill switch, no
statistics. An **experiment** measures: compares variants against a metric and
reaches ship / do-not-ship / inconclusive with statistics.

### Lifecycle (state machine)
`draft → scheduled → running ⇄ paused → completed → archived`. While `running`,
the **core definition is locked** (variants, allocation, unit, primary metric);
only safe edits allowed. To change locked structure, **version** the experiment,
do not mutate.

### Variants & allocation
Exactly one control; weights are whole percentages and **must sum to 100**
(`Allocation` enforces). Optional holdout. Changing allocation mid-flight must
**not re-bucket** assigned units.

### Assignment / bucketing
Deterministic hash of `salt : experiment_key : unit_key`, salted per experiment;
sticky and persisted. **Assignment ≠ exposure** — record exposure only when the
unit actually experiences the variant. Exclusions never assigned. **Layers**
give mutual exclusion (a unit enters at most one running experiment per layer).
Idempotent event keys dedupe double-fires.

### Metrics
Reusable; a metric knows only how it is measured (event, type, aggregate,
attribution window, optional value property). Experiments bind metrics to roles
(primary / secondary / guardrail). **Aggregate to the unit of analysis first,
then run statistics across units** — counting raw events inflates significance.
Guardrails are directional thresholds; a breach can auto-pause + alert.

### Statistics & decisions
Default confidence 0.95. Frequentist (p-value, CI) + Bayesian (probability to
beat control, expected loss, credible interval) reported together. **Sequential /
always-valid inference** so the live dashboard is safe to read anytime.
**SRM (Sample Ratio Mismatch) detection** — chi-square observed vs intended
split; a mismatch invalidates results and must be surfaced. Output is an explicit
**ship / do-not-ship / inconclusive** verdict with reasons, never a bare p-value.

### Governance
Owner per experiment/flag; audit log of privileged actions; optional approval
workflow; retention/archival; stale-flag detection.

---

## 6. Storage model (PostgreSQL)

Append-only raw events are the source of truth; rollups are a derived cache.
Postgres for JSONB, time-partitioning, partial indexes, window functions. All
field names use full words.

```
experiments            -- operational mirror of a code-defined experiment
  id, key, version, layer, status, owner_id,
  traffic_percentage, killed_at, started_at, stopped_at, created_at

variants               -- snapshot of the variant set per experiment version
  id, experiment_id, key, weight, is_control

assignments            -- sticky deterministic bucketing (one row per unit)
  experiment_id, unit_type, unit_key, variant_key, bucket, assigned_at
  UNIQUE(experiment_id, unit_type, unit_key)

events                 -- SOURCE OF TRUTH, append-only, partitioned by month
  id, experiment_id, unit_type, unit_key, variant_key,
  type (exposure|conversion|metric), metric_key, value,
  properties (jsonb), idempotency_key, occurred_at
  UNIQUE(idempotency_key)

rollups                -- derived cache (dashboard reads this), refreshed by job
  experiment_id, variant_key, metric_key,
  count_of_units, exposures, sum_of_values, sum_of_squared_values,
  conversions, updated_through_event_id
  -- count_of_units + sum_of_values + sum_of_squared_values are the sufficient
  -- statistics both engines need; no raw rescans in the request path

guardrail_breaches     -- experiment_id, metric_key, observed, threshold, at
audit_log              -- actor, action, experiment_id, before, after, at
```

Refresh is an incremental, queued/scheduled rollup job using
`updated_through_event_id` as a watermark.

---

## 7. Dashboard plan

Five surfaces: (1) Experiments overview with health badges (status, traffic,
days running, sample-size progress, SRM ✓/✗, guardrails); (2) Experiment detail
results console — headline verdict with reasons, per-variant table, both engines
side by side, time-series with the always-valid bound, secondary + guardrail
panels; (3) Trust panel — SRM, exposure sanity, dedup stats; (4) Controls —
start/pause/resume/stop/archive, traffic ramp, kill switch, QA overrides, all
privileged + audit-logged; (5) Feature flags + segments.

Light CQRS: dashboard reads `rollups` + a TTL-cached `ResultsService`; never
scans raw `events` in the request path. Configurable path + gate (viewer /
editor / admin). Stack **proposed Livewire 3 (+ Volt)** — not yet confirmed.
Plus decision-recommendation service, notifications, CSV/JSON + raw-event export,
retention (drop event partitions after archive, keep rollups).

---

## 8. Class definitions (current source)

Every file below is the actual scaffolded source. Verified with `php -l`; the two
files using PHP 8.4 property hooks (`Confidence`, `MetricSummary`) require 8.4 to
parse. Layers are presented foundation-first.

### 8.1 Enums (closed vocabularies)

#### `src/Enums/MetricType.php`

```php
<?php

declare(strict_types=1);

namespace ABTests\Enums;

/**
 * The statistical nature of a metric. Determines which test the analysis
 * engine applies and how raw events are reduced to a single observation.
 */
enum MetricType: string
{
    case Binary = 'binary';         // converted or not (proportion)
    case Count = 'count';           // number of occurrences per unit
    case Continuous = 'continuous'; // a real-valued measure per unit (revenue, duration)
    case Ratio = 'ratio';           // numerator / denominator across units
}
```

#### `src/Enums/Aggregate.php`

```php
<?php

declare(strict_types=1);

namespace ABTests\Enums;

/**
 * How a unit's raw events are collapsed into the single observation that
 * enters the statistics. Aggregation always happens at the unit level first.
 */
enum Aggregate: string
{
    case UniqueUnits = 'unique_units'; // a unit counts once if it ever converted
    case Sum = 'sum';                  // total value across the unit's events
    case Average = 'average';          // mean value across the unit's events
    case Count = 'count';              // number of events for the unit
}
```

#### `src/Enums/MetricRole.php`

```php
<?php

declare(strict_types=1);

namespace ABTests\Enums;

/**
 * The part a metric plays within an experiment.
 */
enum MetricRole: string
{
    case Primary = 'primary';     // drives the ship / do-not-ship decision (exactly one)
    case Secondary = 'secondary'; // observed for context, not decisive
    case Guardrail = 'guardrail'; // must not regress beyond an allowed amount
}
```

#### `src/Enums/StatisticalEngine.php`

```php
<?php

declare(strict_types=1);

namespace ABTests\Enums;

/**
 * Which analysis approach to run for an experiment.
 */
enum StatisticalEngine: string
{
    case Frequentist = 'frequentist'; // p-value, confidence interval
    case Bayesian = 'bayesian';       // probability to beat control, expected loss
    case Both = 'both';               // run and display both side by side
}
```

#### `src/Enums/EventType.php`

```php
<?php

declare(strict_types=1);

namespace ABTests\Enums;

/**
 * The kind of recorded event in the append-only event store.
 */
enum EventType: string
{
    case Exposure = 'exposure';     // the unit actually experienced its variant
    case Conversion = 'conversion'; // the unit completed a goal
    case Metric = 'metric';         // an arbitrary measured value (e.g. revenue)
}
```

#### `src/Enums/Environment.php`

```php
<?php

declare(strict_types=1);

namespace ABTests\Enums;

/**
 * Deployment environment a definition is being resolved in. Lets the same
 * flag or experiment behave differently outside production.
 */
enum Environment: string
{
    case Local = 'local';
    case Staging = 'staging';
    case Production = 'production';
}
```

#### `src/Enums/Operator.php`

```php
<?php

declare(strict_types=1);

namespace ABTests\Enums;

/**
 * Comparison operators available to segment targeting rules.
 */
enum Operator: string
{
    case Equals = 'equals';
    case NotEquals = 'not_equals';
    case In = 'in';
    case NotIn = 'not_in';
    case GreaterThan = 'greater_than';
    case LessThan = 'less_than';
}
```

#### `src/Enums/ExperimentStatus.php`

```php
<?php

declare(strict_types=1);

namespace ABTests\Enums;

/**
 * The operational lifecycle of an experiment. This state lives in the
 * database (not in code) and is driven from the dashboard. The transition
 * table is the single source of truth for what the controls may do.
 */
enum ExperimentStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Running = 'running';
    case Paused = 'paused';
    case Completed = 'completed';
    case Archived = 'archived';

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Scheduled, self::Running, self::Archived],
            self::Scheduled => [self::Running, self::Draft, self::Archived],
            self::Running => [self::Paused, self::Completed],
            self::Paused => [self::Running, self::Completed],
            self::Completed => [self::Archived],
            self::Archived => [],
        };
    }

    public function isLive(): bool
    {
        return $this === self::Running;
    }
}
```

### 8.2 Contracts (extension seams)

#### `src/Contracts/Bucketable.php`

```php
<?php

declare(strict_types=1);

namespace ABTests\Contracts;

/**
 * The subject of an assignment: a user, a tenant, a session, a device.
 * This is the "multi-level" seam. An experiment declares which Bucketable
 * implementation it buckets on, so the same code can run user-level or
 * tenant-level experiments without change.
 */
interface Bucketable
{
    /**
     * A stable, globally unique identifier for this unit, used as the input
     * to deterministic bucketing. Must never change for a given subject.
     */
    public function bucketingKey(): string;

    /**
     * Attributes describing this unit, consumed by segment targeting.
     *
     * @return array<string, scalar|array<scalar>|null>
     */
    public function attributes(): array;
}
```

#### `src/Contracts/Variant.php`

```php
<?php

declare(strict_types=1);

namespace ABTests\Contracts;

/**
 * A single arm of an experiment. Implemented by a backed enum that uses the
 * ABTests\Concerns\IsVariant trait, so the enum stays the type-safe,
 * exhaustively matchable source of truth for an experiment's arms.
 */
interface Variant
{
    /** The stable storage key for this variant (the enum's backing value). */
    public function key(): string;

    /** Allocation weight as a whole percentage. All weights must sum to 100. */
    public function weight(): int;

    /** Whether this arm is the baseline every other arm is measured against. */
    public function isControl(): bool;
}
```

#### `src/Contracts/BucketingStrategy.php`

```php
<?php

declare(strict_types=1);

namespace ABTests\Contracts;

/**
 * Maps a unit to a stable position used to pick a variant. The default
 * implementation hashes a salt together with the unit's bucketing key, so
 * assignment is deterministic, sticky, and independent across experiments.
 * Swap this to change the hashing algorithm without touching domain logic.
 */
interface BucketingStrategy
{
    /**
     * Return a position in the half-open interval [0.0, 1.0). The same salt
     * and unit must always yield the same position.
     */
    public function position(string $salt, Bucketable $unit): float;
}
```

#### `src/Contracts/AnalysisEngine.php`

```php
<?php

declare(strict_types=1);

namespace ABTests\Contracts;

use ABTests\Values\AnalysisResult;
use ABTests\Values\Confidence;
use ABTests\Values\MetricSummary;

/**
 * Compares a treatment arm against control for one metric. Frequentist and
 * Bayesian engines each implement this; an experiment may run one or both.
 * Engines receive pre-aggregated sufficient statistics, never raw events.
 */
interface AnalysisEngine
{
    public function compare(
        MetricSummary $control,
        MetricSummary $treatment,
        Confidence $confidence,
    ): AnalysisResult;
}
```

#### `src/Contracts/EventSink.php`

```php
<?php

declare(strict_types=1);

namespace ABTests\Contracts;

use ABTests\Values\RecordedEvent;

/**
 * Destination for raw exposure, conversion, and metric events. The append-only
 * event stream is the source of truth; rollups are derived from it. Default
 * implementations may buffer and batch-insert, or forward to an external
 * analytical store, without the rest of the framework knowing.
 */
interface EventSink
{
    public function record(RecordedEvent $event): void;

    /**
     * @param iterable<RecordedEvent> $events
     */
    public function recordBatch(iterable $events): void;
}
```

### 8.3 Attributes (declarative configuration)

#### `src/Attributes/AsExperiment.php`

```php
<?php

declare(strict_types=1);

namespace ABTests\Attributes;

use Attribute;
use ABTests\Contracts\Bucketable;
use ABTests\Contracts\Variant;

/**
 * Declares a class as an experiment definition and supplies its structural
 * configuration. Structure lives in code and is read-only at runtime; only
 * operational state (status, traffic) lives in the database.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsExperiment
{
    /**
     * @param string                    $key      Stable, kebab-case identifier.
     * @param class-string<Bucketable>  $unit     Which subject this experiment buckets on.
     * @param class-string<Variant>     $variants The backed enum defining the arms.
     * @param string|null               $name     Human label for the dashboard.
     * @param string|null               $layer    Mutual-exclusion namespace; units enter
     *                                            at most one running experiment per layer.
     */
    public function __construct(
        public string $key,
        public string $unit,
        public string $variants,
        public ?string $name = null,
        public ?string $layer = null,
    ) {
    }
}
```

#### `src/Attributes/AsFeatureFlag.php`

```php
<?php

declare(strict_types=1);

namespace ABTests\Attributes;

use Attribute;
use ABTests\Contracts\Bucketable;

/**
 * Declares a class as a feature flag definition. A flag controls exposure
 * (release management); it resolves to a value rather than measuring outcomes.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsFeatureFlag
{
    /**
     * @param string                   $key          Stable, kebab-case identifier.
     * @param class-string<Bucketable> $unit         Which subject this flag resolves for.
     * @param mixed                    $defaultValue Returned when resolution cannot complete
     *                                              (storage unavailable, unknown unit). Fail safe.
     */
    public function __construct(
        public string $key,
        public string $unit,
        public mixed $defaultValue = false,
    ) {
    }
}
```

#### `src/Attributes/AsMetric.php`

```php
<?php

declare(strict_types=1);

namespace ABTests\Attributes;

use Attribute;
use ABTests\Enums\Aggregate;
use ABTests\Enums\MetricType;

/**
 * Declares a reusable metric. An experiment references metrics and assigns
 * them roles (primary / secondary / guardrail) via separate attributes; the
 * metric itself only knows how it is measured.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsMetric
{
    /**
     * @param string      $key                   Stable, kebab-case identifier.
     * @param MetricType  $type                  Selects the statistical test.
     * @param string      $event                 Name of the raw event that feeds this metric.
     * @param Aggregate   $aggregate             How a unit's events collapse to one observation.
     * @param string|null $valueFromProperty     Event property to read for continuous metrics.
     * @param string      $attributionWindow     Time from exposure within which an event counts.
     */
    public function __construct(
        public string $key,
        public MetricType $type,
        public string $event,
        public Aggregate $aggregate = Aggregate::UniqueUnits,
        public ?string $valueFromProperty = null,
        public string $attributionWindow = '7 days',
    ) {
    }
}
```

#### `src/Attributes/AsUnit.php`

```php
<?php

declare(strict_types=1);

namespace ABTests\Attributes;

use Attribute;

/**
 * Declares a class as an assignment unit (a Bucketable implementation) and
 * gives it a stable type key used to namespace assignments and events.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsUnit
{
    public function __construct(public string $key)
    {
    }
}
```

#### `src/Attributes/Control.php`

```php
<?php

declare(strict_types=1);

namespace ABTests\Attributes;

use Attribute;

/**
 * Marks a variant enum case as the control (baseline) arm. Exactly one case
 * per experiment must carry this attribute.
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
final readonly class Control
{
}
```

#### `src/Attributes/Weight.php`

```php
<?php

declare(strict_types=1);

namespace ABTests\Attributes;

use Attribute;

/**
 * Sets the traffic share of a variant enum case as a whole percentage.
 * Every case must carry one, and the weights across an enum must sum to 100.
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
final readonly class Weight
{
    public function __construct(public int $percentage)
    {
    }
}
```

#### `src/Attributes/PrimaryMetric.php`

```php
<?php

declare(strict_types=1);

namespace ABTests\Attributes;

use Attribute;
use ABTests\Metric;

/**
 * Designates the single metric that drives the ship / do-not-ship decision.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class PrimaryMetric
{
    /**
     * @param class-string<Metric> $metric
     */
    public function __construct(public string $metric)
    {
    }
}
```

#### `src/Attributes/SecondaryMetric.php`

```php
<?php

declare(strict_types=1);

namespace ABTests\Attributes;

use Attribute;
use ABTests\Metric;

/**
 * A supporting metric, observed but not decision-driving. Repeatable.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class SecondaryMetric
{
    /**
     * @param class-string<Metric> $metric
     */
    public function __construct(public string $metric)
    {
    }
}
```

#### `src/Attributes/Guardrail.php`

```php
<?php

declare(strict_types=1);

namespace ABTests\Attributes;

use Attribute;
use ABTests\Metric;

/**
 * A metric that must not regress. If a treatment arm degrades it beyond the
 * allowed amount, the breach can pause the experiment and alert owners.
 * Repeatable.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
final readonly class Guardrail
{
    /**
     * @param class-string<Metric> $metric
     * @param float                $maximumRegression Worst tolerated relative drop, e.g. 0.005 for 0.5%.
     */
    public function __construct(
        public string $metric,
        public float $maximumRegression,
    ) {
    }
}
```

#### `src/Attributes/Analysis.php`

```php
<?php

declare(strict_types=1);

namespace ABTests\Attributes;

use Attribute;
use ABTests\Enums\StatisticalEngine;

/**
 * Configures how an experiment's results are computed and read.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Analysis
{
    /**
     * @param StatisticalEngine $engine          Which engine(s) to run.
     * @param float             $confidenceLevel Target confidence, e.g. 0.95.
     * @param bool              $sequential      Use always-valid inference so the live
     *                                          dashboard can be read at any time without
     *                                          inflating false positives.
     */
    public function __construct(
        public StatisticalEngine $engine = StatisticalEngine::Both,
        public float $confidenceLevel = 0.95,
        public bool $sequential = true,
    ) {
    }
}
```

#### `src/Attributes/ResolvesExperiment.php`

```php
<?php

declare(strict_types=1);

namespace ABTests\Attributes;

use Attribute;
use ABTests\Experiment;

/**
 * Binds an experiment to a controller action. Middleware resolves the variant,
 * injects it into the method, and records the exposure when the response is
 * sent. This is the attribute-first ergonomics layer over the fluent API.
 */
#[Attribute(Attribute::TARGET_METHOD)]
final readonly class ResolvesExperiment
{
    /**
     * @param class-string<Experiment> $experiment
     */
    public function __construct(public string $experiment)
    {
    }
}
```

### 8.4 Concerns (enum → Variant via reflection)

#### `src/Concerns/IsVariant.php`

```php
<?php

declare(strict_types=1);

namespace ABTests\Concerns;

use ABTests\Attributes\Control;
use ABTests\Attributes\Weight;
use ABTests\Exceptions\MissingVariantWeight;
use ReflectionEnumBackedCase;

/**
 * Implements the Variant contract for a backed enum by reading the #[Weight]
 * and #[Control] attributes off each case. Keeps the enum the single, fully
 * type-safe declaration of an experiment's arms.
 *
 * @mixin \BackedEnum
 */
trait IsVariant
{
    public function key(): string
    {
        return $this->value;
    }

    public function weight(): int
    {
        $weight = $this->caseAttribute(Weight::class);

        if (! $weight instanceof Weight) {
            throw new MissingVariantWeight(static::class, $this->name);
        }

        return $weight->percentage;
    }

    public function isControl(): bool
    {
        return $this->caseAttribute(Control::class) !== null;
    }

    private function caseAttribute(string $attribute): ?object
    {
        $reflection = new ReflectionEnumBackedCase(static::class, $this->name);
        $attributes = $reflection->getAttributes($attribute);

        if ($attributes === []) {
            return null;
        }

        return $attributes[0]->newInstance();
    }
}
```

### 8.5 Value objects (immutable domain types; computed stats via 8.4 hooks)

#### `src/Values/Confidence.php`

```php
<?php

declare(strict_types=1);

namespace ABTests\Values;

use InvalidArgumentException;

/**
 * A statistical confidence level (e.g. 0.95) and its derived significance
 * threshold. The threshold is a virtual property computed on read.
 */
final class Confidence
{
    public function __construct(public readonly float $level)
    {
        if ($level <= 0.0 || $level >= 1.0) {
            throw new InvalidArgumentException(
                'Confidence level must be between 0 and 1 exclusive.'
            );
        }
    }

    /** The significance threshold (alpha), e.g. 0.05 for a 0.95 level. */
    public float $significanceThreshold {
        get => 1.0 - $this->level;
    }
}
```

#### `src/Values/MetricSummary.php`

```php
<?php

declare(strict_types=1);

namespace ABTests\Values;

use ABTests\Contracts\Variant;

/**
 * Pre-aggregated sufficient statistics for one variant and one metric. These
 * are exactly what both engines need, so the analysis layer never rescans the
 * raw event stream. Derived figures are virtual properties (PHP 8.4 hooks).
 */
final class MetricSummary
{
    public function __construct(
        public readonly Variant $variant,
        public readonly int $countOfUnits,
        public readonly float $sumOfValues,
        public readonly float $sumOfSquaredValues,
        public readonly int $conversions,
    ) {
    }

    public float $mean {
        get => $this->countOfUnits > 0
            ? $this->sumOfValues / $this->countOfUnits
            : 0.0;
    }

    public float $variance {
        get {
            if ($this->countOfUnits < 2) {
                return 0.0;
            }

            $mean = $this->mean;

            return ($this->sumOfSquaredValues / $this->countOfUnits) - ($mean * $mean);
        }
    }

    public float $conversionRate {
        get => $this->countOfUnits > 0
            ? $this->conversions / $this->countOfUnits
            : 0.0;
    }
}
```

#### `src/Values/AnalysisResult.php`

```php
<?php

declare(strict_types=1);

namespace ABTests\Values;

use ABTests\Enums\StatisticalEngine;

/**
 * The outcome of comparing one treatment arm to control for one metric.
 * Nullable fields carry the figures specific to whichever engine produced it.
 */
final readonly class AnalysisResult
{
    /**
     * @param array{0: float, 1: float} $interval Lower and upper bound.
     */
    public function __construct(
        public StatisticalEngine $engine,
        public float $relativeLift,
        public bool $isSignificant,
        public array $interval,
        public ?float $pValue = null,                    // frequentist
        public ?float $probabilityToBeatControl = null,  // bayesian
        public ?float $expectedLoss = null,              // bayesian
    ) {
    }
}
```

#### `src/Values/AnalysisConfiguration.php`

```php
<?php

declare(strict_types=1);

namespace ABTests\Values;

use ABTests\Enums\StatisticalEngine;

/**
 * The normalized analysis settings for an experiment, regardless of whether
 * they came from an #[Analysis] attribute or a database row.
 */
final readonly class AnalysisConfiguration
{
    public function __construct(
        public StatisticalEngine $engine,
        public Confidence $confidence,
        public bool $sequential,
    ) {
    }

    public static function default(): self
    {
        return new self(
            engine: StatisticalEngine::Both,
            confidence: new Confidence(0.95),
            sequential: true,
        );
    }
}
```

#### `src/Values/Allocation.php`

```php
<?php

declare(strict_types=1);

namespace ABTests\Values;

use ABTests\Contracts\Variant;
use ABTests\Exceptions\InvalidAllocation;

/**
 * The validated set of variants for an experiment. Enforces the two invariants
 * that keep assignment sound: weights sum to 100, and exactly one control.
 * Also maps a bucket position to the variant that owns that slice of traffic.
 */
final readonly class Allocation
{
    /**
     * @param list<Variant> $variants
     */
    private function __construct(public array $variants)
    {
    }

    /**
     * @param list<Variant> $variants
     */
    public static function fromVariants(array $variants): self
    {
        if ($variants === []) {
            throw new InvalidAllocation('An experiment must declare at least one variant.');
        }

        $totalWeight = array_sum(array_map(
            static fn (Variant $variant): int => $variant->weight(),
            $variants,
        ));

        if ($totalWeight !== 100) {
            throw new InvalidAllocation(
                "Variant weights must sum to 100, got {$totalWeight}."
            );
        }

        $controls = array_filter(
            $variants,
            static fn (Variant $variant): bool => $variant->isControl(),
        );

        if (count($controls) !== 1) {
            throw new InvalidAllocation(
                'An experiment must declare exactly one control variant.'
            );
        }

        return new self(array_values($variants));
    }

    /**
     * Resolve a bucket position in [0, 1) to the variant that owns it.
     */
    public function variantAt(float $position): Variant
    {
        $cursor = 0.0;

        foreach ($this->variants as $variant) {
            $cursor += $variant->weight() / 100;

            if ($position < $cursor) {
                return $variant;
            }
        }

        return $this->variants[array_key_last($this->variants)];
    }

    public function control(): Variant
    {
        foreach ($this->variants as $variant) {
            if ($variant->isControl()) {
                return $variant;
            }
        }

        throw new InvalidAllocation('No control variant present.');
    }
}
```

#### `src/Values/GenericVariant.php`

```php
<?php

declare(strict_types=1);

namespace ABTests\Values;

use ABTests\Contracts\Variant;

/**
 * A variant defined at runtime rather than as an enum case. This is what makes
 * the package general: experiments created in the dashboard (and stored in the
 * database) produce GenericVariant instances, while code-defined experiments
 * use a backed enum with the IsVariant trait. Both satisfy the Variant contract
 * and flow through the exact same Allocation and resolver.
 */
final readonly class GenericVariant implements Variant
{
    public function __construct(
        private string $key,
        private int $weight,
        private bool $isControl = false,
    ) {
    }

    public function key(): string
    {
        return $this->key;
    }

    public function weight(): int
    {
        return $this->weight;
    }

    public function isControl(): bool
    {
        return $this->isControl;
    }
}
```

#### `src/Values/GenericUnit.php`

```php
<?php

declare(strict_types=1);

namespace ABTests\Values;

use ABTests\Contracts\Bucketable;

/**
 * A bucketable subject built from a plain identifier and a bag of attributes,
 * for cases where writing a dedicated unit class is unnecessary, e.g. a guest
 * visitor keyed by a cookie id. Dedicated classes (UserUnit, TenantUnit) remain
 * the typed option; this is the general escape hatch.
 */
final readonly class GenericUnit implements Bucketable
{
    /**
     * @param array<string, scalar|array<scalar>|null> $attributes
     */
    public function __construct(
        private string $key,
        private array $attributes = [],
    ) {
    }

    public function bucketingKey(): string
    {
        return $this->key;
    }

    public function attributes(): array
    {
        return $this->attributes;
    }
}
```

#### `src/Values/Criterion.php`

```php
<?php

declare(strict_types=1);

namespace ABTests\Values;

use ABTests\Enums\Operator;

/**
 * A single targeting rule: one unit attribute compared against an expected
 * value using one operator.
 */
final readonly class Criterion
{
    public function __construct(
        public string $attribute,
        public Operator $operator,
        public mixed $expected,
    ) {
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function matches(array $attributes): bool
    {
        $actual = $attributes[$this->attribute] ?? null;

        return match ($this->operator) {
            Operator::Equals => $actual === $this->expected,
            Operator::NotEquals => $actual !== $this->expected,
            Operator::In => is_array($this->expected) && in_array($actual, $this->expected, true),
            Operator::NotIn => is_array($this->expected) && ! in_array($actual, $this->expected, true),
            Operator::GreaterThan => $actual !== null && $actual > $this->expected,
            Operator::LessThan => $actual !== null && $actual < $this->expected,
        };
    }
}
```

#### `src/Values/Segment.php`

```php
<?php

declare(strict_types=1);

namespace ABTests\Values;

use ABTests\Contracts\Bucketable;
use ABTests\Enums\Operator;

/**
 * A reusable, immutable audience definition built from a conjunction of
 * criteria. Returned from an experiment's audience() method to scope who is
 * eligible. Every with*()/and() call returns a new instance.
 */
final readonly class Segment
{
    /**
     * @param list<Criterion> $criteria
     */
    private function __construct(public array $criteria)
    {
    }

    /** Everyone is eligible. */
    public static function any(): self
    {
        return new self([]);
    }

    public static function where(
        string $attribute,
        mixed $value,
        Operator $operator = Operator::Equals,
    ): self {
        return self::any()->and($attribute, $value, $operator);
    }

    public function and(
        string $attribute,
        mixed $value,
        Operator $operator = Operator::Equals,
    ): self {
        return new self([
            ...$this->criteria,
            new Criterion($attribute, $operator, $value),
        ]);
    }

    public function matches(Bucketable $unit): bool
    {
        $attributes = $unit->attributes();

        foreach ($this->criteria as $criterion) {
            if (! $criterion->matches($attributes)) {
                return false;
            }
        }

        return true;
    }
}
```

#### `src/Values/Context.php`

```php
<?php

declare(strict_types=1);

namespace ABTests\Values;

use ABTests\Contracts\Bucketable;
use ABTests\Enums\Environment;

/**
 * The immutable resolution context handed to a flag's resolve() method. It
 * carries the unit, the environment, and the unit's already-computed bucket
 * position for this definition, so resolution stays a pure function.
 */
final readonly class Context
{
    /**
     * @param float $position Bucket position in [0, 1) for the current definition.
     */
    public function __construct(
        public Bucketable $unit,
        public Environment $environment,
        public float $position,
    ) {
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->unit->attributes()[$key] ?? $default;
    }

    /** True for the given percentage slice of units, stably and deterministically. */
    public function inRollout(int $percentage): bool
    {
        return $this->position < ($percentage / 100);
    }
}
```

#### `src/Values/RecordedEvent.php`

```php
<?php

declare(strict_types=1);

namespace ABTests\Values;

use DateTimeImmutable;
use ABTests\Enums\EventType;

/**
 * An immutable row destined for the append-only event store. The idempotency
 * key lets the sink discard duplicate fires (e.g. a re-rendered exposure)
 * without corrupting counts.
 */
final readonly class RecordedEvent
{
    /**
     * @param array<string, mixed> $properties
     */
    public function __construct(
        public string $experimentKey,
        public string $unitType,
        public string $unitKey,
        public string $variantKey,
        public EventType $type,
        public string $idempotencyKey,
        public DateTimeImmutable $occurredAt,
        public ?string $metricKey = null,
        public ?float $value = null,
        public array $properties = [],
    ) {
    }
}
```

### 8.6 Definitions (source-agnostic normalized representation)

#### `src/Definitions/MetricBinding.php`

```php
<?php

declare(strict_types=1);

namespace ABTests\Definitions;

use ABTests\Enums\MetricRole;

/**
 * Associates a metric with the role it plays in one experiment. A guardrail
 * additionally carries the worst tolerated regression.
 */
final readonly class MetricBinding
{
    /**
     * @param string $metric A metric class-string (code-defined) or key (runtime-defined).
     */
    public function __construct(
        public string $metric,
        public MetricRole $role,
        public ?float $maximumRegression = null,
    ) {
    }
}
```

#### `src/Definitions/ExperimentDefinition.php`

```php
<?php

declare(strict_types=1);

namespace ABTests\Definitions;

use ABTests\Enums\MetricRole;
use ABTests\Values\Allocation;
use ABTests\Values\AnalysisConfiguration;
use ABTests\Values\Segment;

/**
 * The normalized, framework-agnostic representation of an experiment. This is
 * the contract between the front-ends and the engine: the attribute reader
 * produces one of these from a decorated class, and a database loader produces
 * the same thing from dashboard-created rows. Everything downstream (resolver,
 * analysis, dashboard) consumes only this, never the original source.
 */
final readonly class ExperimentDefinition
{
    /**
     * @param string          $unitType The unit's stable type key (e.g. "tenant").
     * @param list<MetricBinding> $metrics
     */
    public function __construct(
        public string $key,
        public string $unitType,
        public Allocation $allocation,
        public AnalysisConfiguration $analysis,
        public Segment $audience,
        public array $metrics,
        public ?string $name = null,
        public ?string $layer = null,
    ) {
    }

    public function primaryMetric(): MetricBinding
    {
        foreach ($this->metrics as $metric) {
            if ($metric->role === MetricRole::Primary) {
                return $metric;
            }
        }

        throw new \LogicException("Experiment [{$this->key}] has no primary metric.");
    }

    /**
     * @return list<MetricBinding>
     */
    public function guardrails(): array
    {
        return array_values(array_filter(
            $this->metrics,
            static fn (MetricBinding $metric): bool => $metric->role === MetricRole::Guardrail,
        ));
    }
}
```

### 8.7 Exceptions

#### `src/Exceptions/ABTestingException.php`

```php
<?php

declare(strict_types=1);

namespace ABTests\Exceptions;

/**
 * Marker implemented by every exception the framework throws, so consumers
 * can catch all package failures with a single type.
 */
interface ABTestingException extends \Throwable
{
}
```

#### `src/Exceptions/InvalidAllocation.php`

```php
<?php

declare(strict_types=1);

namespace ABTests\Exceptions;

final class InvalidAllocation extends \DomainException implements ABTestingException
{
}
```

#### `src/Exceptions/MissingVariantWeight.php`

```php
<?php

declare(strict_types=1);

namespace ABTests\Exceptions;

final class MissingVariantWeight extends \LogicException implements ABTestingException
{
    public function __construct(string $enum, string $case)
    {
        parent::__construct(
            "Variant {$enum}::{$case} is missing a #[Weight(...)] attribute."
        );
    }
}
```

### 8.8 Base classes (consumers extend these)

#### `src/Experiment.php`

```php
<?php

declare(strict_types=1);

namespace ABTests;

use ABTests\Values\Segment;

/**
 * Base class for experiment definitions. Structural configuration is declared
 * with #[AsExperiment] and the metric/analysis attributes; this class only
 * exposes behavioural hooks consumers may override.
 */
abstract class Experiment
{
    /**
     * Scope the experiment to an eligible audience. Units outside the segment
     * are never assigned (they are excluded, not placed in control).
     */
    public function audience(): Segment
    {
        return Segment::any();
    }
}
```

#### `src/FeatureFlag.php`

```php
<?php

declare(strict_types=1);

namespace ABTests;

use ABTests\Values\Context;

/**
 * Base class for feature flag definitions. A flag controls exposure and
 * resolves to a value. Structural configuration is declared with
 * #[AsFeatureFlag]; the resolution rule lives in resolve().
 */
abstract class FeatureFlag
{
    /**
     * Decide this flag's value for the given context. Must be a pure function
     * of the context so the result is deterministic and cacheable.
     */
    abstract public function resolve(Context $context): mixed;

    /**
     * Convenience for percentage rollouts inside resolve().
     */
    protected function rollout(int $percentage, Context $context): bool
    {
        return $context->inRollout($percentage);
    }
}
```

#### `src/Metric.php`

```php
<?php

declare(strict_types=1);

namespace ABTests;

/**
 * Base class for metric definitions. Configuration (event, type, aggregate,
 * attribution window) is declared with #[AsMetric]. Override valueOf() only
 * when deriving a continuous value needs custom logic beyond reading a single
 * event property.
 */
abstract class Metric
{
    /**
     * Derive this metric's numeric contribution from a recorded event's
     * properties. Defaults to 1.0 so a presence-based (binary) metric counts
     * each qualifying event once.
     *
     * @param array<string, mixed> $properties
     */
    public function valueOf(array $properties): float
    {
        return 1.0;
    }
}
```

---

## 9. Worked examples (the two front-ends)

Both produce the same `ExperimentDefinition`; the engine never knows the source.

#### `examples/CodeDefinedExperiment.php`

```php
<?php

declare(strict_types=1);

/**
 * Front-end 1: code-defined (typed) experiment. Maximum compile-time safety.
 * Everything here is the consumer's own application code. Self-contained so the
 * relationships are unambiguous.
 */

namespace App\Experiments;

use ABTests\Attributes\Analysis;
use ABTests\Attributes\AsExperiment;
use ABTests\Attributes\AsMetric;
use ABTests\Attributes\AsUnit;
use ABTests\Attributes\Control;
use ABTests\Attributes\Guardrail;
use ABTests\Attributes\PrimaryMetric;
use ABTests\Attributes\Weight;
use ABTests\Concerns\IsVariant;
use ABTests\Contracts\Bucketable;
use ABTests\Contracts\Variant;
use ABTests\Enums\Aggregate;
use ABTests\Enums\MetricType;
use ABTests\Enums\Operator;
use ABTests\Enums\StatisticalEngine;
use ABTests\Experiment;
use ABTests\Metric;
use ABTests\Values\Segment;

/** The arms: a backed enum is the exhaustively matchable source of truth. */
enum ButtonColor: string implements Variant
{
    use IsVariant;

    #[Control]
    #[Weight(50)]
    case Control = 'control';

    #[Weight(25)]
    case Green = 'green';

    #[Weight(25)]
    case Blue = 'blue';
}

/** The assignment unit: this experiment buckets per tenant, not per user. */
#[AsUnit(key: 'tenant')]
final readonly class TenantUnit implements Bucketable
{
    /** @param object{id: int|string, plan: string, country: string, seats: int} $tenant */
    public function __construct(private object $tenant)
    {
    }

    public function bucketingKey(): string
    {
        return "tenant:{$this->tenant->id}";
    }

    public function attributes(): array
    {
        return [
            'plan' => $this->tenant->plan,
            'country' => $this->tenant->country,
            'seats' => $this->tenant->seats,
        ];
    }
}

#[AsMetric(key: 'checkout-conversion', type: MetricType::Binary, event: 'checkout.completed')]
final class CheckoutConversion extends Metric
{
}

#[AsMetric(key: 'error-rate', type: MetricType::Continuous, event: 'request.failed', aggregate: Aggregate::Average)]
final class ErrorRate extends Metric
{
}

/** The experiment: all structure declared, no magic strings anywhere. */
#[AsExperiment(
    key: 'checkout-button-color',
    unit: TenantUnit::class,
    variants: ButtonColor::class,
    name: 'Checkout button colour',
    layer: 'checkout',
)]
#[PrimaryMetric(CheckoutConversion::class)]
#[Guardrail(ErrorRate::class, maximumRegression: 0.005)]
#[Analysis(engine: StatisticalEngine::Both, confidenceLevel: 0.95, sequential: true)]
final class CheckoutButtonColor extends Experiment
{
    public function audience(): Segment
    {
        return Segment::where('plan', 'pro')
            ->and('country', ['US', 'CA'], Operator::In);
    }
}

/*
 | Resolution at the call site (target ergonomics):
 |
 |   $variant = Experiments::for($tenant)->variant(CheckoutButtonColor::class);
 |
 |   return match ($variant) {
 |       ButtonColor::Green   => view('checkout.green'),
 |       ButtonColor::Blue    => view('checkout.blue'),
 |       ButtonColor::Control => view('checkout.default'),
 |   };
 |
 |   Experiments::track(CheckoutConversion::class, for: $tenant);
 */
```

#### `examples/RuntimeDefinedExperiment.php`

```php
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
        new MetricBinding('checkout-conversion', MetricRole::Primary),
        new MetricBinding('error-rate', MetricRole::Guardrail, maximumRegression: 0.005),
    ],
    name: 'Checkout button colour',
    layer: 'checkout',
);

// A subject built from a plain id + attributes (e.g. a guest, or a hydrated row).
$unit = new GenericUnit(key: 'tenant:42', attributes: ['plan' => 'pro', 'country' => 'US']);

// Same downstream API as the typed example — the engine never knows the source.
// $variant = Experiments::for($unit)->variantOf($definition);
// Experiments::track('checkout-conversion', for: $unit);
```

---

## 10. Engineering requirements for upcoming work

- **Async event ingestion** — buffer exposures/conversions through a queued,
  batch-inserting `EventSink`; never synchronous per-request DB writes.
- **Resolution is a pure function** of context — deterministic, cacheable,
  zero-flicker, no blocking external calls.
- **First-class testing utilities** for consumers — `Experiments::fake()`,
  `forceVariant(...)`, assertions like `assertExposed()` / `assertConverted()`.
- **Server/client consistency** — expose the server-resolved assignment to the
  front end rather than re-hashing in JS.
- **Privacy** — PII boundaries, consent gating for tracking, retention controls.

---

## 11. Target developer experience

```php
// Code-defined, typed:
$variant = Experiments::for($tenant)->variant(CheckoutButtonColor::class);

return match ($variant) {
    ButtonColor::Green   => view('checkout.green'),
    ButtonColor::Blue    => view('checkout.blue'),
    ButtonColor::Control => view('checkout.default'),
};

Experiments::track(CheckoutConversion::class, for: $tenant);

// Controller attribute form (middleware resolves + records exposure):
#[ResolvesExperiment(CheckoutButtonColor::class)]
public function show(Tenant $tenant, ButtonColor $variant): View { /* ... */ }
```

No magic strings in code-defined usage. Runtime-defined usage operates on the
same engine via `ExperimentDefinition` + `GenericVariant` / `GenericUnit`.

---

## 12. Open questions (do not resolve silently)

1. **Dashboard stack:** Livewire 3 (proposed) vs Inertia + Vue/React. Presentation
   layer only; storage/metrics design unaffected.
2. **Statistics default:** confirm `sequential: true` (always-valid) as default.
3. **Runtime-definable metrics:** can a metric be defined purely as data
   (key + event + type) for dashboard-created experiments — requiring a
   `GenericMetric` + a dashboard metric builder — or are metrics always
   code-defined `#[AsMetric]` classes referenced by key? `MetricBinding` holds a
   string today, so it supports either; the answer shapes the schema.
4. **`Metric::valueOf()`** defaults to `1.0`; continuous metrics' `valueFromProperty`
   wiring currently belongs to the registry, not the base class. Confirm.
5. **`Context` single `position`** assumes one bucketing pass per resolution;
   layered experiments compute position per layer in the resolver.

---

## 13. Next build step (proposed — pick one)

- **Resolution pipeline + Registry** — attribute discovery & caching plus the
  resolver (eligibility → segment → holdout → bucketing → sticky persistence →
  exposure) turning an `ExperimentDefinition` into an assignment; or
- **Default Strategy implementations** — the hashing `BucketingStrategy` and the
  frequentist + Bayesian `AnalysisEngine`s, giving the contracts concrete
  behavior.

---

## Appendix — file index

```
src/
  Attributes/   Analysis, AsExperiment, AsFeatureFlag, AsMetric, AsUnit,
                Control, Guardrail, PrimaryMetric, ResolvesExperiment,
                SecondaryMetric, Weight
  Concerns/     IsVariant
  Contracts/    AnalysisEngine, Bucketable, BucketingStrategy, EventSink, Variant
  Definitions/  ExperimentDefinition, MetricBinding
  Enums/        Aggregate, Environment, EventType, ExperimentStatus, MetricRole,
                MetricType, Operator, StatisticalEngine
  Exceptions/   ABTestingException, InvalidAllocation, MissingVariantWeight
  Values/       Allocation, AnalysisConfiguration, AnalysisResult, Confidence,
                Context, Criterion, GenericUnit, GenericVariant, MetricSummary,
                RecordedEvent, Segment
  Experiment.php   FeatureFlag.php   Metric.php
examples/
  CodeDefinedExperiment.php
  RuntimeDefinedExperiment.php
composer.json     CLAUDE.md
```

Not yet built: resolver, registry, strategy implementations, Eloquent
repositories + migrations, event sink, statistics engines, dashboard, service
provider, facade, Blade directives, middleware, testing utilities.
