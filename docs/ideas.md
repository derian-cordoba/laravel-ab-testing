# Ideas & Improvement Proposals

> Generated on 2026-09-04. Based on a full audit of v1.3.1 (17.6k LOC, 99 test files, 284 classes).
> Organized by category. Each idea notes its effort level (S / M / L / XL) and impact (low / medium / high).

---

## Table of Contents

1. [Architecture & Refactoring](#1-architecture--refactoring)
2. [Developer Experience (DX)](#2-developer-experience-dx)
3. [Feature Additions](#3-feature-additions)
4. [Performance & Scalability](#4-performance--scalability)
5. [Testing & Quality](#5-testing--quality)
6. [Dashboard & UI](#6-dashboard--ui)
7. [API](#7-api)
8. [Statistics & Analysis](#8-statistics--analysis)
9. [Open Questions to Resolve](#9-open-questions-to-resolve)

---

## 1. Architecture & Refactoring

### 1.1 Move Base Classes into Bounded Contexts (Pending #1)
**Effort:** M | **Impact:** Medium

`Experiment.php`, `FeatureFlag.php`, and `Metric.php` live at the `src/` root, outside any bounded context. They belong logically in `src/Domain/Experiment/`, `src/Domain/FeatureFlag/`, and `src/Domain/Analysis/` respectively.

**Plan:**
- Move the classes into their bounded-context folders.
- Keep backward-compat shims at the old path (`class_alias`) annotated `@deprecated`.
- Emit a deprecation notice in the constructor body once, removable in a major version.

**Why it matters:** Enforcing bounded-context ownership makes `deptrac` rules possible (see 1.2) and signals to contributors that domain logic belongs inside its context, not at the package root.

---

### 1.2 Enforce Layer Boundaries with Deptrac (Pending #10)
**Effort:** M | **Impact:** High

There are no automated guardrails preventing accidental cross-layer imports (e.g., `Presentation\` importing directly from `Infrastructure\`). As the codebase grows, these leaks become hard to detect.

**Plan:**
- Add `qossmic/deptrac` as a dev dependency.
- Define a `deptrac.yaml` that encodes the allowed dependency directions:
  - `Domain` → nothing outside itself
  - `Application` → `Domain`, `Contracts`
  - `Infrastructure` → `Domain`, `Contracts`, `Application`
  - `Presentation` → `Application`, `Domain` (read-only VOs), `Contracts`
- Run `deptrac analyse` in CI.

**Why it matters:** Every new file added to the project is automatically validated against the hexagonal architecture without code review burden.

---

### 1.3 Split ABTestingServiceProvider into Focused Providers
**Effort:** M | **Impact:** Medium

`ABTestingServiceProvider.php` is 605 LOC and wires everything: storage, analysis, resolution, presentation, notifications, scheduling. This makes it hard to override a single slice (e.g., swap the storage driver) without extending the entire class.

**Plan:** Extract focused inner providers that `ABTestingServiceProvider` defers to:
- `StorageServiceProvider` — all repository bindings + storage driver selection
- `AnalysisServiceProvider` — engines, CUPED, SRM, ResultsService
- `ResolutionServiceProvider` — Resolver + 8 pipeline steps
- `PresentationServiceProvider` — Blade directives, middleware aliases, Livewire namespace
- `SchedulingServiceProvider` — RefreshRollupsJob, PruneEventDataJob

Each would be independently overridable in the host app's `app.php`.

---

### 1.4 Decouple ResultsService from ExperimentModel
**Effort:** M | **Impact:** Medium

`ResultsService` (noted in architecture plan) still reads `ExperimentModel` directly instead of going through `ExperimentRepository`. This breaks the Dependency Inversion principle established in Phase 2.

**Plan:**
- Extend `ExperimentRepository` contract with any additional read methods `ResultsService` needs.
- Replace `ExperimentModel` references in `ResultsService` with repository calls.
- Add `ExperimentResultsRecord` VO if the data shape differs enough from `ExperimentRecord`.

---

### 1.5 Decompose ExperimentResolver
**Effort:** S | **Impact:** Low

`ExperimentResolver.php` is 356 LOC and mixes three concerns: resolution (pipeline invocation), exposure recording, and public API convenience methods. Extracting an `ExposureRecorder` helper (records exposure events without polluting the resolver logic) would make each piece testable in isolation.

---

### 1.6 Introduce a FeatureFlagAggregate Root
**Effort:** M | **Impact:** Medium

The `ExperimentAggregate` pattern (Phase 3) was a significant improvement for experiments. Feature flag lifecycle (enable, disable, kill switch, rollout %, conditions) has equivalent invariants scattered across their command handlers.

**Plan:** Create `src/Domain/FeatureFlag/FeatureFlagAggregate.php` mirroring the experiment aggregate pattern — state transitions, event collection, and `pendingChanges()` — then update the 6 flag command handlers to delegate to it.

---

## 2. Developer Experience (DX)

### 2.1 Artisan Lifecycle Commands
**Effort:** S | **Impact:** High

There are no CLI commands to manage experiment/flag lifecycle. Operators must use the dashboard or API, which is a friction point in CI/CD pipelines.

**Proposed commands:**
```bash
php artisan ab:start   {key}
php artisan ab:pause   {key}
php artisan ab:stop    {key}
php artisan ab:resume  {key}
php artisan ab:archive {key}
php artisan ab:ramp    {key} {percentage}

php artisan ab:flag:enable  {key}
php artisan ab:flag:disable {key}
php artisan ab:flag:rollout {key} {percentage}
```

Each would dispatch the matching command through `CommandBus`. These are thin wrappers — no new logic, just a CLI entry point.

---

### 2.2 Make Stubs (Code Generation)
**Effort:** S | **Impact:** High

Today developers must write `Experiment`, `FeatureFlag`, and `Metric` subclasses from scratch. Add artisan stubs:

```bash
php artisan ab:make:experiment  CheckoutFlowExperiment
php artisan ab:make:flag        NewCheckoutEnabled
php artisan ab:make:metric      ConversionRateMetric
```

Stubs should include `#[AsExperiment]` / `#[AsFeatureFlag]` / `#[AsMetric]` with placeholder values and inline docblock explaining each option. Follow the same pattern as `php artisan make:model`.

---

### 2.3 Richer IDE Support (PHPDoc & Generic Types)
**Effort:** S | **Impact:** Medium

`Experiments::for(Bucketable)` returns `ExperimentResolver`, and `expose(ExperimentClass)` returns `Variant|null`. IDEs cannot infer which concrete `Variant` subclass is returned. Adding `@template` annotations to the facade and resolver would enable auto-complete for variant-specific methods.

---

### 2.4 Language Files for Dashboard Copy
**Effort:** S | **Impact:** Medium

All user-visible strings in Blade/Livewire views are hard-coded English. Extracting them into `resources/lang/en/ab-testing.php` (and `resources/lang/vendor/ab-testing/`) would allow host applications to publish and override copy — or translate it — without modifying package views.

---

### 2.5 Publishable Stubs
**Effort:** S | **Impact:** Medium

Allow users to publish the base `Experiment`, `FeatureFlag`, and `Metric` class stubs so they can customize the defaults (e.g., pre-wire a custom `AnalysisConfiguration` or default environments) for their project:

```bash
php artisan vendor:publish --tag=ab-testing-stubs
```

---

## 3. Feature Additions

### 3.1 Scheduled Experiment Start & Stop
**Effort:** M | **Impact:** High

Add `start_at` and `stop_at` columns to `ab_testing_experiments`. A scheduled task (or the existing `RefreshRollupsJob`) would automatically transition experiments at the configured UTC timestamps.

This eliminates the need for someone to manually press "Start" at midnight, which is a common gap in experimentation platforms.

**Schema change:**
```sql
ALTER TABLE ab_testing_experiments
    ADD COLUMN start_at TIMESTAMP NULL,
    ADD COLUMN stop_at  TIMESTAMP NULL;
```

**Aggregate methods:** `scheduleStart(CarbonImmutable)`, `scheduleStop(CarbonImmutable)`.

---

### 3.2 Experiment Clone / Duplicate
**Effort:** S | **Impact:** Medium

A "Duplicate experiment" action in the dashboard (and a matching `CloneExperimentCommand`) would copy an experiment's definition (variants, metrics, targeting, analysis config) into a new draft. Useful for iterating on a stopped experiment without rewriting from scratch.

---

### 3.3 Experiment Templates
**Effort:** M | **Impact:** Medium

Allow users to save any experiment definition as a reusable template. Templates would be stored in the database or as a config array and surfaced in the "Create Experiment" form's "Start from template" flow.

**Scope:** `ab_testing_experiment_templates` table; `CreateFromTemplateCommand`; Livewire template picker.

---

### 3.4 Reusable Segment Library
**Effort:** M | **Impact:** High

Today, targeting criteria (segments) are defined inline per experiment. There is no way to define "Premium users" once and reuse it across multiple experiments and feature flags.

**Plan:**
- Add `ab_testing_segments` table (name, description, criteria JSON, conditions_logic).
- `SegmentRepository` contract + `DatabaseSegmentRepository`.
- `CheckSegmentStep` can resolve a segment by key instead of requiring inline criteria.
- Dashboard: segment management CRUD + picker in experiment/flag forms.

---

### 3.5 Webhook Delivery Retry
**Effort:** M | **Impact:** High

The current `NotificationDispatcher` sends HTTP requests synchronously in a queued job. If the endpoint is down, the notification is silently dropped.

**Plan:**
- Introduce a `WebhookDeliveryLog` table (url, payload_hash, status_code, attempt_count, last_attempted_at, delivered_at).
- Retry failed deliveries with exponential back-off (3 attempts: 1 min, 5 min, 30 min).
- Surface delivery history in the dashboard notification settings panel.
- Respect a `X-Signature-Timestamp` header to prevent replay attacks beyond a configurable window.

---

### 3.6 GDPR Data Export (Right to Access)
**Effort:** S | **Impact:** Medium

`ForgetUnitCommand` covers the right-to-erasure (GDPR Article 17). There is no corresponding right-to-access (Article 15) — export everything stored about a given unit.

**Plan:** `ExportUnitDataCommand` → collects all `ab_testing_assignments`, `ab_testing_events`, and `ab_testing_covariates` rows for a given `unit_type` + `unit_key` → returns a JSON structure suitable for handing to the user.

---

### 3.7 Event-Driven Metric Collection via Listeners
**Effort:** M | **Impact:** High

Currently, callers must explicitly call `Experiments::track(MetricClass, for: $unit, value: $value)`. Many Laravel apps already dispatch events (e.g., `OrderPlaced`, `UserSubscribed`). The package could auto-track metrics by listening to Laravel events.

**Plan:**
- Add an optional `events` key to `#[AsMetric]`:
  ```php
  #[AsMetric(key: 'revenue', type: MetricType::Normal, event: 'App\Events\OrderPlaced')]
  ```
- `ABTestingServiceProvider` registers a generic listener that intercepts the configured events and calls `Experiments::track()` automatically.

This is a zero-boilerplate path that makes adoption much faster.

---

### 3.8 Feature Flag Condition Presets
**Effort:** S | **Impact:** Medium

The conditions builder in the dashboard currently requires manually adding criteria. Add a "preset" system for common targeting conditions:
- Internal users (email ends in `@company.com`)
- Beta group (user attribute `is_beta = true`)
- Country targeting
- Plan tier targeting

Presets would be configurable in `ab-testing.php` and surfaced as a one-click option in the condition builder.

---

### 3.9 Experiment Conflict Detection
**Effort:** M | **Impact:** Medium

The current layer system prevents units from entering two experiments in the same layer. But there is no warning at experiment creation/start time when two experiments target overlapping audiences in the same layer. The conflict is silently handled at resolution time.

**Plan:** At `StartExperimentCommand` dispatch time, run a conflict-detection check: if another running experiment in the same layer has an overlapping traffic range and identical (or no) audience criteria, emit a `PotentialExperimentConflictWarning` domain event and surface it in the dashboard as a yellow banner. Do not block the start — warn only.

---

## 4. Performance & Scalability

### 4.1 Redis Assignment Cache Layer
**Effort:** M | **Impact:** High

For high-traffic applications, every variant resolution hits `ab_testing_assignments` for sticky lookup. On a 10k-req/s service, this adds significant DB load.

**Plan:**
- Add an optional `CachingAssignmentRepository` decorator that wraps `DatabaseAssignmentRepository` with a Redis cache (TTL configurable, keyed by `unit_type:unit_key:experiment_key`).
- Wire it in `StorageServiceProvider` when `storage.cache.driver = 'redis'` is set in config.
- Cache invalidation: `ForgetUnitCommand` flushes the unit's keys.

---

### 4.2 Parallelizable Rollup Job
**Effort:** L | **Impact:** High

`RefreshRollupsJob` processes all running experiments sequentially. A large installation with many concurrent experiments could experience rollup lag.

**Plan:**
- Add a `RefreshRollupsDispatcherJob` (5-min scheduler) that fans out a `RefreshSingleExperimentRollupJob` per running experiment.
- Each child job holds its own watermark and processes only one experiment's events.
- Cap concurrency via a `WithoutOverlapping` mutex per experiment key.

---

### 4.3 Cache Tag-Based Invalidation for ResultsService
**Effort:** S | **Impact:** Medium

`ResultsService` uses a TTL-based cache (5-min default). When an experiment is stopped, or a rollup finishes, the cached result is stale until TTL expires. Cache tags (supported by Redis/Memcached drivers) would allow instant invalidation.

**Plan:**
- Tag each cached entry with `['ab-testing', "ab-results:{$experimentKey}"]`.
- `StopExperimentCommandHandler` and `RollupAggregator` flush the tag on write.
- Graceful degradation: fall back to TTL-based for drivers that don't support tags.

---

### 4.4 High-Throughput Event Sink via Redis Queue
**Effort:** L | **Impact:** High

`DatabaseEventSink` buffers events in-process and flushes at request termination via a `Terminable` middleware. This works for standard PHP-FPM but is unreliable in long-running processes (Octane, Horizon workers) where `terminate()` may not fire on every request.

**Plan:**
- Add a `RedisEventSink` adapter that pushes serialized events to a Redis list (LPUSH).
- A dedicated `FlushRedisEventSinkJob` pops batches (LRANGE + LTRIM) and inserts into PostgreSQL.
- Makes the sink durable and decoupled from request lifecycle.

---

## 5. Testing & Quality

### 5.1 Mutation Testing with Infection
**Effort:** M | **Impact:** High

The package has 529 tests passing. But passing tests ≠ good tests. Mutation testing runs the test suite against automatically mutated versions of the source code to detect tests that never actually exercise the logic they cover.

**Plan:**
- Add `infection/infection` as a dev dependency.
- Configure `infection.json5` targeting `src/Domain/` first (highest value).
- Add a CI step that fails if Mutation Score Indicator (MSI) drops below a threshold (e.g., 80%).

---

### 5.2 Architecture Tests with PHPArkitect
**Effort:** S | **Impact:** Medium

As an alternative (or complement) to deptrac, `phparkitect/phparkitect` provides a PHPUnit-style DSL for architecture assertions:

```php
Rule::allClasses()
    ->that(ResideInOneOfTheseNamespaces::that('ABTests\Domain'))
    ->should(NotDependOnTheseNamespaces::matching('ABTests\Infrastructure'))
    ->because('domain must not depend on infrastructure');
```

These could live in `tests/Architecture/` and run as part of the standard test suite.

---

### 5.3 Model Factories for Test Setup
**Effort:** S | **Impact:** Medium

Integration tests currently construct test data manually. Adding Eloquent model factories for `ExperimentModel`, `VariantModel`, `AssignmentModel`, `EventModel`, `RollupModel`, etc. would drastically reduce boilerplate and make it easier for host-app developers to write their own integration tests against the package.

**Files:** `database/factories/ExperimentModelFactory.php`, etc., loadable via `ABTestingServiceProvider`.

---

### 5.4 Contract Tests for Repository Implementations
**Effort:** M | **Impact:** Medium

`DatabaseAssignmentRepository` and `InMemoryAssignmentRepository` implement the same `AssignmentRepository` contract. Today their tests are written independently. A shared contract test trait would guarantee both implementations satisfy the same behavioral contract:

```php
trait AssignmentRepositoryContractTest {
    abstract protected function makeRepository(): AssignmentRepository;

    public function test_stores_and_finds_assignment(): void { ... }
    public function test_idempotent_on_duplicate_store(): void { ... }
}
```

Any new storage adapter (e.g., a Redis implementation) automatically gets the full contract suite.

---

## 6. Dashboard & UI

### 6.1 Time-Series Chart for Feature Flag Evaluations
**Effort:** M | **Impact:** Medium

The experiment dashboard shows an `ExperimentTimeSeriesChart`. Feature flags have no equivalent visualization. A chart showing evaluation rate (enabled vs disabled) over time — backed by a new `ab_testing_flag_evaluations` rollup — would give visibility into flag adoption and kill-switch effectiveness.

---

### 6.2 Dashboard Search & Filtering
**Effort:** S | **Impact:** High

`ExperimentsOverview` and `FeatureFlagsOverview` list all records. As the number grows, filtering and search become necessary:
- Search by name/key (real-time Livewire `wire:model.live`)
- Filter by status (running, paused, completed, archived)
- Filter by environment
- Sort by created_at, started_at, status

---

### 6.3 Experiment Results Export
**Effort:** S | **Impact:** Medium

There is an `ExperimentExport` Livewire component. Make it concrete: export the full results (variant statistics, analysis result, confidence intervals, CUPED-adjusted values) as:
- CSV (for analysts)
- JSON (for downstream tooling)
- PDF summary (for stakeholders)

---

### 6.4 Stale Flag Detection Dashboard Panel
**Effort:** S | **Impact:** Medium

`DetectStaleFlagsCommand` runs via CLI. Surface its output in the dashboard as a dedicated "Stale Flags" panel: flags past the configured threshold with no evaluation activity, with a one-click "archive" action.

---

### 6.5 QA Override Persistence
**Effort:** S | **Impact:** High

The `QaOverrides` Livewire component lets engineers force a variant for testing. Currently these overrides appear to be in-memory / session-scoped. Persisting QA overrides in the database (associated to a unit type + unit key, flagged as `is_qa_override = true`) would let overrides survive server restarts and work in multi-server environments (Kubernetes, etc.).

---

### 6.6 Dark Mode for Dashboard
**Effort:** M | **Impact:** Low

The dashboard uses Tailwind CSS. Dark mode support (via `class` strategy + a `dark:` prefix toggle button in the navbar) would improve usability for developers who keep dark mode on their machines.

---

## 7. API

### 7.1 Rate Limiting for Management API
**Effort:** S | **Impact:** Medium

The management API (experiments CRUD, flag toggles) has no rate limiting. Adding a `throttle:60,1` (60 rpm per token) middleware to `api.v1.middleware` defaults and documenting it in `config/ab-testing.php` would prevent accidental or malicious abuse.

---

### 7.2 Versioned API with Explicit Deprecation Path
**Effort:** M | **Impact:** Medium

The API routes live under `api/v1/`. Adding a clear deprecation and versioning strategy now — before v2 endpoints are needed — avoids a painful migration later:
- Add a `Deprecated` response header on v1 routes once v2 is available.
- Document the versioning strategy in `docs/api.md`.
- Provide a migration guide file (`docs/api-v2-migration.md`) when breaking changes are made.

---

### 7.3 SDK-style JavaScript Snippet
**Effort:** L | **Impact:** High

For frontend (SPA, SSR) use cases, provide a tiny JavaScript module that calls the assignments API endpoint and exposes the result:

```js
import { ABTesting } from '@derian-cordoba/laravel-ab-testing';

const ab = new ABTesting({ unitKey: user.id });
const variant = await ab.resolve('checkout-flow-experiment');
if (variant === 'new_checkout') { ... }
```

This would make the package useful for server-rendered React/Vue apps without requiring SSR PHP rendering.

---

## 8. Statistics & Analysis

### 8.1 Bayesian SRM Detection
**Effort:** M | **Impact:** Medium

`SampleRatioMismatchDetector` uses a chi-squared test. A Bayesian alternative would compute the posterior probability that the observed assignment ratio differs from the intended one — more interpretable for teams already using the Bayesian engine.

---

### 8.2 Configurable Bayesian Prior
**Effort:** S | **Impact:** Medium

`BayesianAnalysisEngine` uses a fixed prior `τ = 1.0` (a weakly informative normal prior). For teams with historical baseline data, a configurable prior (via `#[Analysis(prior_tau: 0.5)]` or the config file) would improve early-stopping accuracy.

---

### 8.3 Multi-Metric Correction (Bonferroni / BH)
**Effort:** M | **Impact:** High

When an experiment has multiple metrics, the probability of at least one false positive increases with each additional metric (multiple comparisons problem). Add optional Bonferroni or Benjamini-Hochberg correction applied at the `VerdictResolver` level when `metric_count > 1`.

**Config:**
```php
'multiple_comparisons_correction' => 'bonferroni', // 'bonferroni' | 'bh' | null
```

---

### 8.4 Long-Running Experiment Warnings
**Effort:** S | **Impact:** Medium

Add a domain rule (enforced in `GuardrailEvaluationService` or a new `ExperimentHealthService`) that flags experiments running longer than `target_sample_size` would require at current traffic. Long-running experiments suffer from novelty effects and peeking bias. Surface a warning banner in `ExperimentDetail` when elapsed days > recommended duration by 150%.

---

### 8.5 Minimum Detectable Effect Calculator in Dashboard
**Effort:** S | **Impact:** Medium

`PowerAnalysisCommand` exists as a CLI tool and `ExperimentPowerAnalysis` as a Livewire component. Make the MDE calculator interactive: as the user adjusts traffic percentage, variant count, and confidence level, the required sample size and estimated duration update in real time via `wire:model.live`.

---

## 9. Open Questions to Resolve

These are carried over from the project's documented open questions. They block specific implementation paths.

| # | Question | Blocks |
|---|----------|--------|
| OQ-1 | **Dashboard stack:** Livewire 3 (current proposal) vs Inertia + Vue/React | Any future dashboard rebuild |
| OQ-2 | **Sequential default:** confirm `sequential: true` as the default in `#[Analysis]` | `AnalysisConfiguration` default + docs |
| OQ-3 | **Runtime-definable metrics:** can metrics be created purely as data via the dashboard, or always code-defined `#[AsMetric]` classes? | Dashboard metric builder, `MetricBinding` schema |
| OQ-4 | **`Metric::valueOf()` wiring:** does the `valueFromProperty` default (1.0) belong in the registry or the base class? | `Metric` base class design |
| OQ-5 | **`Context` single `position`:** assumes one bucketing pass per resolution; layered experiments compute position per layer | `Context` VO, `BucketStep`, layered experiment resolver |

---

## Priority Matrix

| Idea | Effort | Impact | Suggested Order |
|------|--------|--------|-----------------|
| 2.1 Artisan lifecycle commands | S | High | 1 |
| 2.2 Make stubs | S | High | 2 |
| 3.7 Event-driven metric collection | M | High | 3 |
| 6.2 Dashboard search & filtering | S | High | 4 |
| 3.1 Scheduled start/stop | M | High | 5 |
| 1.2 Deptrac layer enforcement | M | High | 6 |
| 4.1 Redis assignment cache | M | High | 7 |
| 3.4 Reusable segment library | M | High | 8 |
| 1.3 Split ABTestingServiceProvider | M | Medium | 9 |
| 1.6 FeatureFlagAggregate | M | Medium | 10 |
| 1.4 Decouple ResultsService | M | Medium | 11 |
| 5.1 Mutation testing | M | High | 12 |
| 8.3 Multi-metric correction | M | High | 13 |
| 3.5 Webhook retry | M | High | 14 |
| 3.2 Experiment clone | S | Medium | 15 |
| 5.4 Contract tests for repos | M | Medium | 16 |
| 5.3 Model factories | S | Medium | 17 |
| 4.2 Parallelizable rollup job | L | High | 18 |
| 4.4 Redis event sink | L | High | 19 |
| 7.3 JS SDK snippet | L | High | 20 |
