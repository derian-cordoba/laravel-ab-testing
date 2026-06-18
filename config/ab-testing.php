<?php

declare(strict_types=1);

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
    | Auto-discovery
    |--------------------------------------------------------------------------
    |
    | When enabled, the service provider will scan the listed paths at boot and
    | register any Experiment or FeatureFlag subclass it finds — so you don't
    | have to list every class in 'experiments' or 'flags.register' above.
    | `php artisan ab:cache` also honors these paths when building the manifest.
    |
    | Disabled by default to avoid unexpected behavior on boot.
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

    /*
    |--------------------------------------------------------------------------
    | Feature flags
    |--------------------------------------------------------------------------
    |
    |   register             — List every FeatureFlag subclass decorated with
    |                          #[AsFeatureFlag] that the package should resolve.
    |                          The service provider reads these at boot and
    |                          populates the FeatureFlagRegistry via AttributeReader.
    |
    |                          Example:
    |                            'register' => [
    |                                \App\Flags\NewCheckoutFlag::class,
    |                            ],
    |
    |   stale_threshold_days — A flag that is still enabled but has not been
    |                          evaluated (or touched) in this many days is
    |                          marked stale in the dashboard. Set to 0 to
    |                          disable stale detection entirely.
    |
    */

    'feature_flags' => [
        'register' => [],
        'stale_threshold_days' => (int) env('AB_TESTING_STALE_FLAG_DAYS', 90),
    ],

    /*
    |--------------------------------------------------------------------------
    | Governance
    |--------------------------------------------------------------------------
    |
    |   approval_required     — When true, an experiment must receive an
    |                           explicit Approve action before it can transition
    |                           from draft/scheduled → running. The StartExperiment
    |                           command will throw if no approval record exists.
    |
    |   require_power_analysis — When true, StartExperiment will emit a warning
    |                            (but not block) if target_sample_size has not
    |                            been set on the experiment. Set to 'block' to
    |                            hard-block the transition instead.
    |
    */

    'governance' => [
        'approval_required' => (bool) env('AB_TESTING_APPROVAL_REQUIRED', false),
        'require_power_analysis' => env('AB_TESTING_REQUIRE_POWER_ANALYSIS', 'warn'), // 'off' | 'warn' | 'block'
    ],

    /*
    |--------------------------------------------------------------------------
    | Privacy
    |--------------------------------------------------------------------------
    |
    |   consent_resolver — Optional fully-qualified class name implementing
    |                      ABTests\Contracts\ConsentResolver, or null to skip
    |                      consent checks. When set, the event sink will call
    |                      resolver->hasConsented($unitType, $unitKey) before
    |                      writing any tracking event. Units without consent
    |                      are still assigned a variant (bucketing still runs)
    |                      but no events are recorded for them.
    |
    */

    'privacy' => [
        'consent_resolver' => env('AB_TESTING_CONSENT_RESOLVER'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Data retention
    |--------------------------------------------------------------------------
    |
    |   days             — Events belonging to archived experiments older than
    |                      this many days are deleted by PruneEventDataJob.
    |                      Rollup rows are kept indefinitely (they are tiny).
    |                      Set to 0 to disable automatic pruning.
    |
    |   auto_schedule    — Register PruneEventDataJob in the Laravel scheduler
    |                      automatically (weekly, on Sundays at midnight).
    |
    */

    'retention' => [
        'days' => (int) env('AB_TESTING_RETENTION_DAYS', 365),
        'auto_schedule' => (bool) env('AB_TESTING_AUTO_SCHEDULE_PRUNING', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Public API
    |--------------------------------------------------------------------------
    |
    | Versioned API configuration. Each version key (v1, v2, …) declares the
    | vendor media type that callers must send in their Accept header, shared
    | middleware applied to every endpoint within that version, and the set of
    | individual endpoints.
    |
    |   accept_type  — Vendor media type for this API version. Using a versioned
    |                  vendor type prevents casual browser access and provides
    |                  implicit versioning. In production unrecognized requests
    |                  receive a 404; in other environments a 406.
    |
    |   middleware   — Additional middleware applied to every endpoint in this
    |                  version. The dashboard middleware stack is always prepended.
    |
    |   endpoints
    |     assignments — Expose server-resolved assignments for the current request
    |                   so front-end code can read the same variant without
    |                   re-hashing.
    |
    |       enabled  — Register the GET /{path} route for this endpoint.
    |       path     — URL path relative to the dashboard prefix.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Outbound Notifications
    |--------------------------------------------------------------------------
    |
    | When enabled, the package dispatches a queued job after each supported
    | lifecycle event, delivering a notification to every enabled channel.
    |
    |   enabled  — Master switch. When false no jobs are queued regardless of
    |              channel or event configuration.
    |
    |   events   — Per-event opt-in/out. Set a key to false to suppress that
    |              event type across all channels without disabling others.
    |
    |   channels
    |     webhook  — HTTP POST with an HMAC-SHA256 signature.
    |       url      — Endpoint that receives the POST.
    |       secret   — Signing secret used for HMAC. Leave empty to skip signing.
    |       timeout  — HTTP client timeout in seconds (default 5).
    |
    |     slack    — Slack incoming webhook (Block Kit attachment).
    |       webhook_url — Incoming webhook URL from Slack app settings.
    |
    |     mail     — Email delivery via Laravel's Mail facade.
    |       recipients  — List of email addresses that receive every notification.
    |
    |   queue_connection — Queue connection for DispatchNotificationsJob.
    |                      Defaults to the application's default connection.
    |   queue_name       — Queue name (default 'default'). Use a dedicated
    |                      low-priority queue to isolate notification retries.
    |
    */

    'notifications' => [
        'enabled' => (bool) env('AB_TESTING_NOTIFICATIONS_ENABLED', false),

        'events' => [
            'experiment_started' => (bool) env('AB_TESTING_NOTIFY_EXPERIMENT_STARTED', true),
            'experiment_paused' => (bool) env('AB_TESTING_NOTIFY_EXPERIMENT_PAUSED', true),
            'experiment_resumed' => (bool) env('AB_TESTING_NOTIFY_EXPERIMENT_RESUMED', true),
            'experiment_stopped' => (bool) env('AB_TESTING_NOTIFY_EXPERIMENT_STOPPED', true),
            'feature_flag_enabled' => (bool) env('AB_TESTING_NOTIFY_FLAG_ENABLED', false),
            'feature_flag_disabled' => (bool) env('AB_TESTING_NOTIFY_FLAG_DISABLED', false),
            'kill_switch_activated' => (bool) env('AB_TESTING_NOTIFY_KILL_SWITCH', true),
            'guardrail_breached' => (bool) env('AB_TESTING_NOTIFY_GUARDRAIL_BREACHED', true),
        ],

        'channels' => [
            'webhook' => [
                'enabled' => (bool) env('AB_TESTING_WEBHOOK_ENABLED', false),
                'url' => env('AB_TESTING_WEBHOOK_URL'),
                'secret' => env('AB_TESTING_WEBHOOK_SECRET', ''),
                'timeout' => (int) env('AB_TESTING_WEBHOOK_TIMEOUT', 5),
            ],

            'slack' => [
                'enabled' => (bool) env('AB_TESTING_SLACK_ENABLED', false),
                'webhook_url' => env('AB_TESTING_SLACK_WEBHOOK_URL'),
            ],

            'mail' => [
                'enabled' => (bool) env('AB_TESTING_MAIL_ENABLED', false),
                'recipients' => array_filter(explode(',', env('AB_TESTING_MAIL_RECIPIENTS', ''))),
            ],
        ],

        'queue_connection' => env('AB_TESTING_NOTIFICATION_QUEUE_CONNECTION'),
        'queue_name' => env('AB_TESTING_NOTIFICATION_QUEUE', 'default'),
    ],

    'api' => [
        'v1' => [
            'accept_type' => env('AB_TESTING_ACCEPT_TYPE', 'application/vnd.ab-testing.v1+json'),
            'middleware' => ['api'],

            /*
            |--------------------------------------------------------------------------
            | Management API gate
            |--------------------------------------------------------------------------
            |
            |   manage_gate — Gate name checked by RequiresApiAccess on every write
            |                 endpoint (create, update, lifecycle, variants). If the
            |                 gate is not defined, all requests are allowed through.
            |                 Define the gate in AuthServiceProvider to restrict access.
            |
            |                 Example:
            |                   Gate::define('manageAbTestingApi', fn ($user) => $user->isAdmin());
            |
            */

            'manage_gate' => env('AB_TESTING_API_MANAGE_GATE', 'manageAbTestingApi'),

            'endpoints' => [

                /*
                | assignments — Expose server-resolved assignments for the current
                |               request so front-end code can read the same variant
                |               without re-hashing. Mounted under the dashboard prefix.
                */
                'assignments' => [
                    'enabled' => (bool) env('AB_TESTING_ASSIGNMENTS_ENDPOINT', true),
                    'path' => 'assignments',
                ],

                /*
                | experiments — Full REST management API. Enables CI/CD integration:
                |               create, start, stop, check verdict, ship or rollback.
                |
                |   prefix — URL prefix for all management API routes.
                |             Default: api/v1/ab-testing → /api/v1/ab-testing/experiments
                */
                'experiments' => [
                    'enabled' => (bool) env('AB_TESTING_EXPERIMENTS_API_ENABLED', true),
                    'prefix' => env('AB_TESTING_API_PREFIX', 'api/v1/ab-testing'),
                ],
            ],
        ],
    ],
];
