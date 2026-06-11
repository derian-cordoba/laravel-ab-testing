# Dashboard Implementation Plan

**Package:** `derian-cordoba/laravel-ab-testing`
**Stack:** PHP 8.4 · Laravel 13 · Livewire 3 · Alpine.js · Tailwind CSS (CDN)
**Status:** In development — no stable release yet

---

## Contents

- [Overview](#overview)
- [V1 scope decisions](#v1-scope-decisions)
- [Architecture](#architecture)
- [Database schema additions](#database-schema-additions)
- [Implementation steps](#implementation-steps)
- [File map](#file-map)
- [Open questions (future work)](#open-questions-future-work)

---

## Overview

The dashboard is a package-provided UI that any consumer application can mount at a
configurable route (default `/ab-testing`), similar to Laravel Horizon or Telescope.
It covers five surfaces:

1. **Experiments overview** — list with health badges (status, traffic %, days running, assigned units, SRM, guardrails).
2. **Experiment detail / results console** — headline verdict, per-variant stats table, frequentist + Bayesian side by side.
3. **Trust panel** — SRM details, dedup event statistics (tab inside detail).
4. **Controls** — start / pause / resume / stop / archive, traffic ramp, kill switch. All privileged and audit-logged.
5. **Feature flags** — stub surface (full implementation in v2).

---

## V1 scope decisions

The following open questions from the planning phase are resolved as follows for v1.
All decisions are documented here rather than decided silently.

| # | Question | V1 decision | Rationale |
|---|---|---|---|
| 1 | `events` table uses `experiment_key` (string) or `experiment_id` (int FK)? | **`experiment_key` string** | Matches the existing `RecordedEvent` VO; no lookup required on ingest; simpler for a dev-phase package. Migrate to FK in v2 if performance requires it. |
| 2 | Incremental `count_of_units` (distinct unit count)? | **Full recount per rollup cycle** — `COUNT(DISTINCT unit_key)` via SQL | Correct, zero extra tables, acceptable at dev-phase scale. Add a staging table or HyperLogLog sketch in v2 when row counts grow. |
| 3 | `target_sample_size` for progress badge? | **Nullable column on `ab_testing_experiments`** — user enters it manually via the controls panel or leaves it null (badge hides) | Computed power analysis belongs in v2 when metrics history is available. |
| 4 | Feature flag DB state table? | **Added** (`ab_testing_feature_flag_states`) but **no dashboard UI in v1** — stub link only | Ensures the schema is ready; avoids a premature UI surface. |
| 5 | QA overrides? | **Deferred to v2** | Requires a resolver pipeline step change; out of scope for v1. |
| 6 | Runtime-defined metric events in rollups? | **Code-defined experiments only in v1** | The rollup job reads metric keys from registered `ExperimentDefinition`s; runtime-defined experiments have no code-side metric registry yet. |
| 7 | Livewire as hard `require` vs optional? | **Hard `require`** | The dashboard is a first-class feature; making Livewire optional would require a separate sub-package. Revisit if adoption data suggests many users skip the dashboard. |

---

## Architecture

```
HTTP request (dashboard route)
    │
    ▼
RequiresDashboardAccess middleware (gate check)
    │
    ▼
Livewire component  ─────────── read ──────► ResultsService (TTL-cached)
    │                                              │
    │ dispatch command                             ▼
    ▼                                        RollupModel rows
SynchronousCommandBus                             │
    │                                             ▼
    ▼                                        AnalysisService
CommandHandler                                    │
  ├─ validate transition                          ▼
  ├─ update ExperimentModel                  VerdictResult + AnalysisResult DTOs
  ├─ write AuditLogModel
  ├─ fire domain event
  └─ flush results cache

Background (queue/schedule)
  RefreshRollupsJob  ─── reads ──► ab_testing_events
                     ─── writes ─► ab_testing_rollups
                                ─► ab_testing_guardrail_breaches
                                ─► GuardrailBreachedEvent ─► AutoPauseOnGuardrailBreachListener
```

### CQRS pattern

- **Reads:** `ResultsService` reads only `ab_testing_rollups`. Never scans `ab_testing_events`
  in the request path. Results are TTL-cached (default 60 s) and tag-flushed on state changes.
- **Writes:** All state mutations go through the `CommandBus`. Commands are plain
  `final readonly` data carriers; handlers contain all mutation logic and write the audit log.

---

## Database schema additions

Five new migrations beyond the existing two:

### `ab_testing_events` (source of truth, append-only)

```
experiment_key    string  — string key of the experiment
unit_type         string
unit_key          string
variant_key       string
type              string  — EventType enum: exposure | conversion | metric
metric_key        string? — null for exposure events
value             double? — null for non-continuous events
properties        json?
idempotency_key   string  UNIQUE
occurred_at       timestamp

indexes:
  UNIQUE(idempotency_key)
  (experiment_key, occurred_at)
  (experiment_key, variant_key, metric_key, occurred_at)
```

### `ab_testing_rollups` (derived cache)

```
experiment_key         string
variant_key            string
metric_key             string
count_of_units         bigint   — COUNT(DISTINCT unit_key) for exposure events
exposures              bigint
sum_of_values          double
sum_of_squared_values  double
conversions            bigint
updated_through_at     timestamp?  — watermark: last occurred_at processed
updated_at             timestamp?

UNIQUE(experiment_key, variant_key, metric_key)
```

### `ab_testing_guardrail_breaches`

```
id                bigint PK
experiment_key    string
metric_key        string
variant_key       string
observed_value    double
threshold_value   double
breached_at       timestamp
is_acknowledged   boolean default false
acknowledged_at   timestamp?
```

### `ab_testing_audit_log`

```
id                  bigint PK
actor_identifier    string?
actor_type          string?   — 'user' | 'system'
action              string    — start | pause | resume | stop | archive | kill | ramp_traffic
experiment_key      string?
before_state        json?
after_state         json?
occurred_at         timestamp
```

### `ab_testing_feature_flag_states` (schema-only in v1, no dashboard UI)

```
id                   bigint PK
key                  string UNIQUE
is_enabled           boolean default false
rollout_percentage   int default 0
killed_at            timestamp?
timestamps
```

### `ab_testing_experiments` — new nullable column

```
target_sample_size   int?   — manually entered; used for progress badge
```

---

## Implementation steps

Steps are ordered by dependency. Steps within the same number can be built in parallel.

### Step 1 — Database migrations + models

**Migrations** (6 new files in `database/migrations/`):
- `…_000003_create_ab_testing_events_table`
- `…_000004_create_ab_testing_rollups_table`
- `…_000005_create_ab_testing_guardrail_breaches_table`
- `…_000006_create_ab_testing_audit_log_table`
- `…_000007_create_ab_testing_feature_flag_states_table`
- `…_000008_add_target_sample_size_to_ab_testing_experiments_table`

**Models** (4 new files in `src/Infrastructure/Database/Models/`):
- `EventModel` — `ab_testing_events`, no timestamps, `properties` cast to array
- `RollupModel` — `ab_testing_rollups`, unique on `(experiment_key, variant_key, metric_key)`
- `GuardrailBreachModel` — `ab_testing_guardrail_breaches`
- `AuditLogModel` — `ab_testing_audit_log`, no timestamps (has `occurred_at`)
- `FeatureFlagStateModel` — `ab_testing_feature_flag_states`

### Step 2 — DatabaseEventSink

**File:** `src/Infrastructure/Database/DatabaseEventSink.php`

Replaces `NullEventSink` when `storage.driver = 'database'`. Buffers `RecordedEvent` objects in-process; flushes via a `terminating()` callback registered in the service provider. Batch-inserts using `EventModel::query()->insertOrIgnore([...])` with idempotency guaranteed by the unique constraint on `idempotency_key`.

### Step 3 — RefreshRollupsJob (depends on Step 1)

**File:** `src/Infrastructure/Jobs/RefreshRollupsJob.php`

Watermarked incremental rollup:
1. For each experiment in `running` or `paused` state:
   a. Find the current `updated_through_at` watermark for this experiment's rollups.
   b. Fetch events newer than the watermark, chunked at 5,000 rows.
   c. Compute sufficient statistics per `(experiment_key, variant_key, metric_key)`.
   d. `count_of_units` is computed via `COUNT(DISTINCT unit_key)` full recount (v1 simplification).
   e. Upsert rollup rows.
   f. Check guardrail thresholds against the updated rollups; insert breach rows and fire `GuardrailBreachedEvent` for new breaches.
2. Scheduled via the service provider's `boot()` (every minute, guarded by config).

### Step 4 — Domain events + listener (depends on Step 1)

**Files:**
- `src/Domain/Events/GuardrailBreachedEvent.php`
- `src/Application/Listeners/AutoPauseOnGuardrailBreachListener.php`

Listener dispatches `PauseExperimentCommand` with `actorIdentifier = 'system'` when a breach fires.

### Step 5 — Command bus + commands + handlers (depends on Step 1)

**Contracts:**
- `src/Contracts/CommandBus.php`

**Implementation:**
- `src/Application/SynchronousCommandBus.php` — resolves handler by class-name convention

**Commands** (7 files in `src/Application/Commands/`):
`StartExperimentCommand`, `PauseExperimentCommand`, `ResumeExperimentCommand`,
`StopExperimentCommand`, `ArchiveExperimentCommand`, `ToggleKillSwitchCommand`, `RampTrafficCommand`

**Handlers** (7 files in `src/Application/CommandHandlers/`):
Each handler: load model → validate transition → snapshot before → mutate → snapshot after → write audit log → fire event → flush cache.

**Exceptions:**
- `src/Exceptions/InvalidStateTransition.php`

### Step 6 — ResultsService (depends on Steps 1, 3, 5)

**Files:**
- `src/Application/ResultsService.php`
- `src/Application/Data/ExperimentResultsData.php`
- `src/Application/Data/VariantResultData.php`

Reads rollup rows, hydrates `MetricSummary` VOs, delegates to `AnalysisService`, caches the `ExperimentResultsData` DTO per experiment key with a configurable TTL. Returns `null` when no rollup data exists yet (experiment not yet running or no events recorded).

### Step 7 — Dashboard routing + middleware (depends on Step 5)

**Files:**
- `src/Dashboard/routes.php`
- `src/Dashboard/Http/Middleware/RequiresDashboardAccess.php`

Route group uses configurable prefix, middleware stack, and gate checks from config.

### Step 8 — Livewire components (depends on Steps 6, 7)

**Files:**
- `src/Dashboard/Livewire/ExperimentsOverview.php`
- `src/Dashboard/Livewire/ExperimentDetail.php`
- `src/Dashboard/Livewire/ExperimentControls.php`

### Step 9 — Views (depends on Step 8)

**Files in `resources/views/`:**
- `layout.blade.php` — Tailwind CDN, Alpine.js CDN, Livewire scripts
- `livewire/experiments-overview.blade.php`
- `livewire/experiment-detail.blade.php`
- `livewire/experiment-controls.blade.php`

### Step 10 — Service provider + config updates (depends on all steps)

**`config/ab-testing.php`** — new `dashboard` and `events` blocks.

**`ABTestingServiceProvider`** — bind `CommandBus`, `ResultsService`, `DatabaseEventSink`;
register Livewire components; load views and routes; register schedule and event listener.

**`composer.json`** — add `livewire/livewire: ^3.0` to `require`.

---

## File map

```
src/
  Application/
    Commands/
      ArchiveExperimentCommand.php
      PauseExperimentCommand.php
      RampTrafficCommand.php
      ResumeExperimentCommand.php
      StartExperimentCommand.php
      StopExperimentCommand.php
      ToggleKillSwitchCommand.php
    CommandHandlers/
      ArchiveExperimentCommandHandler.php
      PauseExperimentCommandHandler.php
      RampTrafficCommandHandler.php
      ResumeExperimentCommandHandler.php
      StartExperimentCommandHandler.php
      StopExperimentCommandHandler.php
      ToggleKillSwitchCommandHandler.php
    Data/
      ExperimentResultsData.php
      VariantResultData.php
    Listeners/
      AutoPauseOnGuardrailBreachListener.php
    ResultsService.php
    SynchronousCommandBus.php
  Contracts/
    CommandBus.php                            ← new
  Dashboard/
    Http/
      Middleware/
        RequiresDashboardAccess.php
    Livewire/
      ExperimentControls.php
      ExperimentDetail.php
      ExperimentsOverview.php
    routes.php
  Domain/
    Events/
      GuardrailBreachedEvent.php
  Exceptions/
    InvalidStateTransition.php               ← new
  Infrastructure/
    Database/
      DatabaseEventSink.php                  ← replaces NullEventSink for database driver
      Models/
        AuditLogModel.php                    ← new
        EventModel.php                       ← new
        FeatureFlagStateModel.php            ← new
        GuardrailBreachModel.php             ← new
        RollupModel.php                      ← new
    Jobs/
      RefreshRollupsJob.php                  ← new

resources/
  views/
    layout.blade.php
    livewire/
      experiment-controls.blade.php
      experiment-detail.blade.php
      experiments-overview.blade.php

database/
  migrations/
    2024_01_01_000003_create_ab_testing_events_table.php
    2024_01_01_000004_create_ab_testing_rollups_table.php
    2024_01_01_000005_create_ab_testing_guardrail_breaches_table.php
    2024_01_01_000006_create_ab_testing_audit_log_table.php
    2024_01_01_000007_create_ab_testing_feature_flag_states_table.php
    2024_01_01_000008_add_target_sample_size_to_ab_testing_experiments_table.php
```

---

## Open questions (future work)

These were deferred from v1 and must be addressed before a stable release:

1. **Incremental `count_of_units`** — full recount is correct but O(n) on events. Add a
   `ab_testing_unit_exposures(experiment_key, variant_key, unit_key)` staging table with a
   `UNIQUE` constraint for O(1) incremental counting when event volume grows.

2. **Runtime-defined experiment metrics** — the rollup job currently skips experiments whose
   definitions are not in the `ExperimentRegistry`. A `ab_testing_metrics` table is needed to
   store metric definitions for dashboard-created experiments.

3. **Time-series chart** — currently a stub. V2 will aggregate rollup `updated_at` buckets
   into time series data and render via a lightweight Alpine.js + SVG chart component.

4. **QA overrides** — requires a `ab_testing_qa_overrides` table and a new
   `CheckQaOverrideStep` in the resolution pipeline before `BucketStep`.

5. **Feature flag dashboard surface** — the state table and model are in place; the
   Livewire page and command handlers need to be built.

6. **Async event persistence** — the current `DatabaseEventSink` flushes synchronously in
   the request termination hook. A queued `PersistEventBatchJob` should be added for
   high-throughput production deployments.

7. **Sample size power calculation** — `target_sample_size` is currently entered manually.
   A v2 power calculator would compute it from the baseline conversion rate, MDE, and
   confidence level.

8. **Asset compilation** — views currently load Tailwind CSS and Alpine.js from CDN.
   Production deployments should publish and compile the assets. A `php artisan
   vendor:publish --tag=ab-testing-assets` flow needs to be designed.
