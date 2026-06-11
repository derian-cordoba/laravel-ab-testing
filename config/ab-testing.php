<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storage driver
    |--------------------------------------------------------------------------
    |
    | Controls which repository implementations are bound for AssignmentRepository
    | and ExperimentStateRepository.
    |
    |   'database'  — Database (PostgreSQL/MySQL). Default. Requires running the
    |                 package migrations: php artisan migrate
    |
    |   'in_memory' — Plain PHP arrays, scoped to the current request/process.
    |                 Suitable for unit tests and local development; assignments
    |                 are never persisted between requests.
    |
    */

    'storage' => [
        'driver' => env('AB_TESTING_DRIVER', 'database'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Registered experiments
    |--------------------------------------------------------------------------
    |
    | List every Experiment subclass decorated with #[AsExperiment] that the
    | package should resolve. The service provider reads these at boot and
    | populates the ExperimentRegistry via AttributeReader.
    |
    | Example:
    |   'experiments' => [
    |       \App\Experiments\CheckoutButtonColor::class,
    |   ],
    |
    */

    'experiments' => [],

    /*
    |--------------------------------------------------------------------------
    | Registered feature flags
    |--------------------------------------------------------------------------
    |
    | List every FeatureFlag subclass decorated with #[AsFeatureFlag] that the
    | package should resolve. The service provider reads these at boot and
    | populates the FeatureFlagRegistry via AttributeReader.
    |
    | Example:
    |   'feature_flags' => [
    |       \App\Flags\NewCheckoutFlag::class,
    |   ],
    |
    */

    'feature_flags' => [],

    /*
    |--------------------------------------------------------------------------
    | Auto-discovery
    |--------------------------------------------------------------------------
    |
    | When enabled, the service provider will scan the listed paths at boot and
    | register any Experiment or FeatureFlag subclass it finds — so you don't
    | have to list every class in 'experiments' or 'feature_flags' above.
    | `php artisan ab:cache` also honours these paths when building the manifest.
    |
    | Disabled by default to avoid unexpected behaviour on boot.
    |
    */

    'discovery' => [
        'enabled' => false,
        'paths' => [
            // app_path('Experiments'),
            // app_path('Flags'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    |
    | Controls the built-in Livewire dashboard.
    |
    |   path               — URL prefix for all dashboard routes (default: 'ab-testing').
    |   middleware         — Middleware stack applied to every dashboard route.
    |   viewer_gate        — Gate name checked by RequiresDashboardAccess. If the gate
    |                        is not defined, all authenticated users are allowed through.
    |   results_cache_ttl_seconds — How long ResultsService caches computed results.
    |   auto_schedule_rollups — Register RefreshRollupsJob in the scheduler automatically.
    |
    */

    'dashboard' => [
        'path' => env('AB_TESTING_DASHBOARD_PATH', 'ab-testing'),
        'middleware' => ['web'],
        'viewer_gate' => 'viewAbTestingDashboard',
        'results_cache_ttl_seconds' => (int) env('AB_TESTING_RESULTS_CACHE_TTL', 300),
        'auto_schedule_rollups' => (bool) env('AB_TESTING_AUTO_SCHEDULE_ROLLUPS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Event ingestion
    |--------------------------------------------------------------------------
    |
    |   batch_size      — Maximum number of events flushed to the database in one
    |                     INSERT batch.
    |   queue_connection — Queue connection used by RefreshRollupsJob.
    |   queue_name       — Queue name for the rollup job.
    |
    */

    'events' => [
        'batch_size' => (int) env('AB_TESTING_EVENT_BATCH_SIZE', 500),
        'queue_connection' => env('AB_TESTING_QUEUE_CONNECTION', 'sync'),
        'queue_name' => env('AB_TESTING_QUEUE_NAME', 'default'),
    ],

];
