# Management API

The package exposes a versioned HTTP API for experiment lifecycle management, variant configuration, and CI/CD integration. All endpoints live under a configurable prefix (default: `/api/ab-testing`) and follow the [JSON:API](https://jsonapi.org) response format.

## Contents

- [Overview](#overview)
- [Media type and versioning](#media-type-and-versioning)
- [Authorization](#authorization)
- [Configuration reference](#configuration-reference)
- [Experiments](#experiments)
  - [List experiments](#list-experiments)
  - [Create an experiment](#create-an-experiment)
  - [Get an experiment](#get-an-experiment)
  - [Update an experiment](#update-an-experiment)
  - [Archive an experiment](#archive-an-experiment)
- [Lifecycle](#lifecycle)
  - [Start](#start)
  - [Pause](#pause)
  - [Resume](#resume)
  - [Stop](#stop)
  - [Ramp traffic](#ramp-traffic)
  - [Kill switch](#kill-switch)
  - [Deactivate kill switch](#deactivate-kill-switch)
- [Variants](#variants)
  - [Add a variant](#add-a-variant)
  - [Update a variant](#update-a-variant)
  - [Remove a variant](#remove-a-variant)
- [Results](#results)
  - [Get results](#get-results)
  - [Get verdict](#get-verdict)
- [Error responses](#error-responses)
- [CI/CD integration example](#cicd-integration-example)

---

## Overview

The API is the programmatic counterpart to the dashboard. Typical uses include:

- provisioning experiments from a deployment pipeline
- driving lifecycle transitions (`start`, `stop`) as part of a release workflow
- polling `/results` to monitor progress
- calling `/verdict` to get a `ship` / `do_not_ship` / `inconclusive` recommendation before a deploy

All write endpoints are protected by the same gate that guards the dashboard management controls (`manageAbTestingApi` by default). Read endpoints follow the same middleware stack.

---

## Media type and versioning

Every request to the API **must** include the configured vendor media type in its `Accept` header. Requests that omit it or send a wildcard such as `*/*` are rejected immediately, before any gate check or controller logic runs.

```http
Accept: application/vnd.ab-testing.v1+json
```

Every response carries the same value as its `Content-Type` header:

```http
Content-Type: application/vnd.ab-testing.v1+json
```

Both behaviors are enforced by middleware registered on the entire API route group:

| Middleware                      | Responsibility                                                                                                                                                                   |
|---------------------------------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `EnforceAcceptHeaderMiddleware` | Rejects requests whose `Accept` header does not exactly match the configured value. Returns `406` in non-production and `404` in production to avoid leaking endpoint existence. |
| `SetApiContentTypeMiddleware`   | Sets `Content-Type` on every outgoing response to the configured value. No controller needs to set it manually.                                                                  |

The vendor media type is driven by the `ab-testing.api.v1.accept_type` config key:

```php
// config/ab-testing.php
'api' => [
    'v1' => [
        'accept_type' => env('AB_TESTING_ACCEPT_TYPE', 'application/vnd.ab-testing.v1+json'),
    ],
],
```

Changing the value here updates both the enforcement check and the response header without touching any controller code.

---

## Authorization

The API gate (`manageAbTestingApi` by default) is checked on every request. The gate name is configurable:

```php
// config/ab-testing.php
'api' => [
    'v1' => [
        'manage_gate' => env('AB_TESTING_API_MANAGE_GATE', 'manageAbTestingApi'),
    ],
],
```

If the gate is **not defined** in the host application, all requests are allowed through. Define the gate in your `AuthServiceProvider` to restrict access:

```php
use Illuminate\Support\Facades\Gate;

Gate::define('manageAbTestingApi', function ($user): bool {
    return $user->hasRole('experimenter');
});
```

When the gate denies access, the API returns `403` in non-production environments and `404` in production.

---

## Configuration reference

```php
// config/ab-testing.php
'api' => [
    'v1' => [
        // Vendor media type required in Accept and set on Content-Type.
        'accept_type' => env('AB_TESTING_ACCEPT_TYPE', 'application/vnd.ab-testing.v1+json'),

        // Additional middleware applied to every endpoint in this version.
        'middleware' => ['api'],

        // Gate name checked on every request.
        'manage_gate' => env('AB_TESTING_API_MANAGE_GATE', 'manageAbTestingApi'),

        'endpoints' => [
            'experiments' => [
                // Set to false to disable all management API routes entirely.
                'enabled' => (bool) env('AB_TESTING_EXPERIMENTS_API_ENABLED', true),

                // URL prefix. Default: api/ab-testing → /api/ab-testing/experiments
                'prefix'  => env('AB_TESTING_API_PREFIX', 'api/ab-testing'),
            ],
        ],
    ],
],
```

---

## Experiments

### List experiments

```
GET /api/ab-testing/experiments
```

Returns a paginated list of all experiments ordered by most recently updated. Accepts an optional `?status=` filter.

**Query parameters**

| Parameter | Type | Description |
|---|---|---|
| `status` | string | Filter by lifecycle status. One of: `draft`, `scheduled`, `running`, `paused`, `completed`, `archived`. |
| `page` | integer | Page number (default: 1). Page size is fixed at 25. |

**Response `200`**

```json
{
  "data": [
    {
      "id": "checkout-button-color",
      "type": "experiments",
      "attributes": {
        "name": "Checkout Button Color",
        "version": null,
        "layer": "checkout",
        "status": "running",
        "traffic_percentage": 100,
        "is_killed": false,
        "killed_at": null,
        "started_at": "2025-01-15T10:00:00+00:00",
        "stopped_at": null,
        "created_at": "2025-01-14T09:00:00+00:00",
        "updated_at": "2025-01-15T10:00:00+00:00",
        "variants": [
          { "id": 1, "key": "control", "weight": 50, "is_control": true },
          { "id": 2, "key": "green",   "weight": 50, "is_control": false }
        ]
      }
    }
  ],
  "links": { "first": "...", "last": "...", "prev": null, "next": null },
  "meta": { "current_page": 1, "total": 1 }
}
```

---

### Create an experiment

```
POST /api/ab-testing/experiments
```

Creates a new experiment in `draft` status. Add variants with the [variants endpoints](#variants) before starting.

**Request body**

| Field | Type | Required | Description |
|---|---|---|---|
| `key` | string | yes | Stable, kebab-case identifier. Must be unique. |
| `name` | string | no | Human-readable label shown in the dashboard. |
| `layer` | string | no | Mutual-exclusion namespace. A unit enters at most one running experiment per layer. |
| `traffic_percentage` | integer | no | Initial traffic percentage (0–100). Defaults to `0`. |

```json
{
  "key": "checkout-button-color",
  "name": "Checkout Button Color",
  "layer": "checkout",
  "traffic_percentage": 0
}
```

**Response `201`** — same shape as [Get an experiment](#get-an-experiment).

**Response `422`** — validation error when `key` is missing or already exists.

---

### Get an experiment

```
GET /api/ab-testing/experiments/{key}
```

Returns the experiment and its current variant set.

**Response `200`**

```json
{
  "data": {
    "id": "checkout-button-color",
    "type": "experiments",
    "attributes": {
      "name": "Checkout Button Color",
      "version": null,
      "layer": "checkout",
      "status": "draft",
      "traffic_percentage": 0,
      "is_killed": false,
      "killed_at": null,
      "started_at": null,
      "stopped_at": null,
      "created_at": "2025-01-14T09:00:00+00:00",
      "updated_at": "2025-01-14T09:00:00+00:00",
      "variants": []
    }
  }
}
```

**Response `404`** — experiment key does not exist.

---

### Update an experiment

```
PUT /api/ab-testing/experiments/{key}
```

Updates editable metadata. `layer` is locked once the experiment leaves `draft` or `scheduled`.

**Request body**

| Field | Type | Description |
|---|---|---|
| `name` | string | Human-readable label. |
| `layer` | string | Mutual-exclusion namespace. Locked after `draft`/`scheduled`. |
| `target_sample_size` | integer | Optional sample size target shown as a progress bar in the dashboard. |

**Response `200`** — updated experiment resource.

**Response `404`** — experiment key does not exist.

---

### Archive an experiment

```
DELETE /api/ab-testing/experiments/{key}
```

Archives the experiment. Only `completed` experiments can be archived. Archived experiments are read-only; no further state transitions are possible.

**Response `204`** — archived successfully, no body.

**Response `404`** — experiment key does not exist.

**Response `422`** — experiment is not in `completed` status.

---

## Lifecycle

All lifecycle endpoints return the updated experiment resource on success (`200`) or `404` if the key does not exist. Attempting an invalid transition (e.g. pausing an already-completed experiment) returns `422`.

### Start

```
POST /api/ab-testing/experiments/{key}/start
```

Transitions `draft` or `scheduled` → `running`. Requires at least two variants (one control, one treatment) with weights summing to 100. If `traffic_percentage` is `0` at start time, it is automatically raised to `100`.

---

### Pause

```
POST /api/ab-testing/experiments/{key}/pause
```

Transitions `running` → `paused`. Assignment and event recording stop until the experiment is resumed.

---

### Resume

```
POST /api/ab-testing/experiments/{key}/resume
```

Transitions `paused` → `running`. Assignment and event recording resume.

---

### Stop

```
POST /api/ab-testing/experiments/{key}/stop
```

Transitions `running` or `paused` → `completed`. No further assignments occur. The experiment is now ready to be analysed and eventually archived.

---

### Ramp traffic

```
POST /api/ab-testing/experiments/{key}/traffic
```

Updates the traffic percentage without changing status. Useful for gradual rollouts: start at `5`, verify metrics, ramp to `50`, then `100`.

**Request body**

| Field | Type | Required | Description |
|---|---|---|---|
| `traffic_percentage` | integer | yes | Target traffic percentage (0–100). |

```json
{ "traffic_percentage": 50 }
```

**Response `422`** — value is outside 0–100.

---

### Kill switch

```
POST /api/ab-testing/experiments/{key}/kill-switch
```

Activates or deactivates the kill switch. When active, all units receive the control variant regardless of their sticky assignment, without recording new assignment or exposure events.

**Request body**

| Field | Type | Default | Description |
|---|---|---|---|
| `is_killed` | boolean | `true` | `true` to activate, `false` to deactivate. |

```json
{ "is_killed": true }
```

---

### Deactivate kill switch

```
POST /api/ab-testing/experiments/{key}/kill-switch/deactivate
```

Convenience alias that always sets `is_killed` to `false`. Equivalent to `POST /kill-switch` with `{ "is_killed": false }`.

---

## Variants

Variants define the arms of an experiment. Weights must always sum to `100` and exactly one variant must be the control before the experiment can be started.

Variant changes on live (`running` or `paused`) experiments are allowed with these guarantees:

- existing sticky assignments are never disturbed
- weight changes only affect future assignments
- adding a variant mid-flight is allowed; already-assigned units are unaffected
- removing a treatment variant mid-flight is allowed; the control cannot be removed
- any mid-flight structural change triggers an SRM warning on the dashboard

All variant write endpoints return the full updated experiment resource (`200`) with the current variant set embedded in `data.attributes.variants`.

---

### Add a variant

```
POST /api/ab-testing/experiments/{key}/variants
```

**Request body**

| Field | Type | Required | Description |
|---|---|---|---|
| `key` | string | yes | Stable, kebab-case variant identifier. |
| `weight` | integer | yes | Traffic weight (1–100). |
| `is_control` | boolean | no | `true` for the control arm. Defaults to `false`. |

```json
{
  "key": "green",
  "weight": 50,
  "is_control": false
}
```

**Response `422`** — `key` or `weight` missing, weight out of range, or experiment not found.

---

### Update a variant

```
PUT /api/ab-testing/experiments/{key}/variants/{id}
```

Updates the variant's key, weight, or control designation. The `id` is the integer primary key returned in the variant list.

**Request body** — same fields as [Add a variant](#add-a-variant), all required.

**Response `404`** — variant does not belong to the given experiment.

---

### Remove a variant

```
DELETE /api/ab-testing/experiments/{key}/variants/{id}
```

Removes a treatment variant. The control variant cannot be removed. On running or paused experiments, at least two variants must remain after removal.

**Response `204`** — removed successfully, no body.

**Response `404`** — variant does not belong to the given experiment.

**Response `422`** — attempting to remove the control, or removal would leave fewer than two variants on a live experiment.

---

## Results

### Get results

```
GET /api/ab-testing/experiments/{key}/results
```

Returns full per-variant statistics for both analysis engines, the SRM diagnostic, and any active guardrail breaches. The payload is exactly what the dashboard results panel renders. Both engines are computed from pre-aggregated rollup data — no raw event scans happen in the request path.

**Response `200`**

```json
{
  "data": {
    "id": "checkout-button-color",
    "type": "experiment-results",
    "attributes": {
      "status": "running",
      "computed_at": "2025-01-20T14:30:00+00:00",
      "total_units": 1000,
      "srm": {
        "detected": false,
        "chi_square": 0.04,
        "p_value": 0.84
      },
      "active_guardrail_breaches": [],
      "variants": [
        {
          "key": "control",
          "is_control": true,
          "primary_metric": {
            "count_of_units": 500,
            "conversions": 50,
            "conversion_rate": 0.1,
            "mean": 0.1
          },
          "verdict": null
        },
        {
          "key": "green",
          "is_control": false,
          "primary_metric": {
            "count_of_units": 500,
            "conversions": 60,
            "conversion_rate": 0.12,
            "mean": 0.12
          },
          "verdict": {
            "recommendation": "ship",
            "label": "Ship",
            "srm_detected": false,
            "frequentist": {
              "relative_lift": 0.2,
              "is_significant": true,
              "confidence_interval": [0.02, 0.38],
              "p_value": 0.031,
              "probability_to_beat_control": null,
              "expected_loss": null
            },
            "bayesian": {
              "relative_lift": 0.19,
              "is_significant": true,
              "confidence_interval": [0.01, 0.37],
              "p_value": null,
              "probability_to_beat_control": 0.96,
              "expected_loss": 0.001
            }
          }
        }
      ]
    }
  }
}
```

**Response `404`** — experiment does not exist, or exists but has no rollup data yet.

```json
{
  "message": "No results available yet for this experiment.",
  "experiment_key": "checkout-button-color"
}
```

---

### Get verdict

```
GET /api/ab-testing/experiments/{key}/verdict
```

The CI/CD decision endpoint. Returns the overall recommendation (`ship` / `do_not_ship` / `inconclusive`) and a per-treatment-variant breakdown. Designed to be polled after `POST /stop` and used to drive a ship-or-rollback decision in a deployment pipeline.

Unlike `/results`, this endpoint returns a flat JSON object (not a JSON:API resource wrapper) so it is straightforward to parse in shell scripts and pipeline tools.

**Response `200` — with data**

```json
{
  "experiment_key": "checkout-button-color",
  "status": "completed",
  "srm_detected": false,
  "overall_recommendation": "ship",
  "computed_at": "2025-01-20T14:30:00+00:00",
  "total_units": 1000,
  "active_guardrail_breaches": 0,
  "variants": [
    {
      "key": "green",
      "recommendation": "ship",
      "label": "Ship",
      "relative_lift": 0.2,
      "is_significant": true,
      "p_value": 0.031,
      "probability_to_beat_control": 0.96,
      "expected_loss": 0.001,
      "count_of_units": 500,
      "conversion_rate": 0.12
    }
  ]
}
```

**Response `200` — no data yet**

```json
{
  "experiment_key": "checkout-button-color",
  "status": "running",
  "srm_detected": false,
  "overall_recommendation": "inconclusive",
  "message": "No results available yet.",
  "variants": []
}
```

**Response `200` — SRM detected**

```json
{
  "experiment_key": "checkout-button-color",
  "status": "completed",
  "srm_detected": true,
  "overall_recommendation": "inconclusive",
  "message": "Sample ratio mismatch detected. Results are invalid. Investigate before shipping.",
  "variants": []
}
```

**Overall recommendation logic**

| Condition | `overall_recommendation` |
|---|---|
| No rollup data | `inconclusive` |
| SRM detected | `inconclusive` |
| Every treatment variant verdict is `ship` | `ship` |
| Any treatment variant verdict is `do_not_ship` | `do_not_ship` |
| Otherwise | `inconclusive` |

**Response `404`** — experiment key does not exist.

---

## Error responses

All error responses follow a consistent shape. Validation errors (`422`) include a field-level breakdown.

**`406 Not Acceptable`** — missing or wrong `Accept` header (non-production only; production returns `404`).

```json
{
  "message": "This endpoint requires Accept: application/vnd.ab-testing.v1+json."
}
```

**`403 Forbidden`** — gate defined and denied (non-production; production returns `404`).

```json
{
  "message": "Access to the A/B testing API is not authorized."
}
```

**`404 Not Found`**

```json
{
  "message": "Experiment not found."
}
```

**`422 Unprocessable Entity`**

```json
{
  "message": "The key field is required.",
  "errors": {
    "key": ["The key field is required."]
  }
}
```

---

## CI/CD integration example

A minimal shell pipeline that creates an experiment, starts it, waits for sample size, stops it, reads the verdict, and exits with a non-zero code if the recommendation is not `ship`.

```bash
#!/usr/bin/env bash
set -euo pipefail

BASE_URL="https://your-app.example.com"
API_PREFIX="${BASE_URL}/api/ab-testing"
ACCEPT="application/vnd.ab-testing.v1+json"
KEY="checkout-button-color"

# 1. Create the experiment
curl -sf -X POST "${API_PREFIX}/experiments" \
  -H "Accept: ${ACCEPT}" \
  -H "Content-Type: application/json" \
  -d '{"key":"'"${KEY}"'","name":"Checkout Button Color","layer":"checkout"}' \
  > /dev/null

# 2. Add variants
curl -sf -X POST "${API_PREFIX}/experiments/${KEY}/variants" \
  -H "Accept: ${ACCEPT}" \
  -H "Content-Type: application/json" \
  -d '{"key":"control","weight":50,"is_control":true}' > /dev/null

curl -sf -X POST "${API_PREFIX}/experiments/${KEY}/variants" \
  -H "Accept: ${ACCEPT}" \
  -H "Content-Type: application/json" \
  -d '{"key":"green","weight":50}' > /dev/null

# 3. Start at 10% traffic
curl -sf -X POST "${API_PREFIX}/experiments/${KEY}/start" \
  -H "Accept: ${ACCEPT}" > /dev/null

curl -sf -X POST "${API_PREFIX}/experiments/${KEY}/traffic" \
  -H "Accept: ${ACCEPT}" \
  -H "Content-Type: application/json" \
  -d '{"traffic_percentage":10}' > /dev/null

# 4. (Wait for enough traffic in your real pipeline)
echo "Experiment running. Waiting for sample..."
sleep 30

# 5. Stop and evaluate
curl -sf -X POST "${API_PREFIX}/experiments/${KEY}/stop" \
  -H "Accept: ${ACCEPT}" > /dev/null

VERDICT=$(curl -sf "${API_PREFIX}/experiments/${KEY}/verdict" \
  -H "Accept: ${ACCEPT}" | jq -r '.overall_recommendation')

echo "Verdict: ${VERDICT}"

if [ "${VERDICT}" != "ship" ]; then
  echo "Not shipping. Recommendation: ${VERDICT}"
  exit 1
fi

echo "All clear. Proceeding with deploy."
```
