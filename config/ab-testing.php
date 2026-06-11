<?php

declare(strict_types=1);

return [

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
    | above. Disabled by default to avoid unexpected behaviour on boot.
    |
    */

    'discovery' => [
        'enabled' => false,
        'paths' => [
            // app_path('Experiments'),
        ],
    ],

];
