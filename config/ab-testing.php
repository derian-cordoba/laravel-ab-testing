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
    | Auto-discovery
    |--------------------------------------------------------------------------
    |
    | When enabled, `php artisan ab:cache` will also scan the listed paths for
    | classes decorated with #[AsExperiment] in addition to the explicit list
    | above. Disabled by default to avoid unexpected behavior on boot.
    |
    */

    'discovery' => [
        'enabled' => false,
        'paths' => [
            // app_path('Experiments'),
        ],
    ],

];
