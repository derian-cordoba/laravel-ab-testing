<?php

declare(strict_types=1);

use Illuminate\Support\ServiceProvider;

return [
    'name'     => 'AB Testing Test App',
    'env'      => 'testing',
    'debug'    => true,
    'key'      => 'base64:dGVzdGtleXRlc3RrZXl0ZXN0a2V5dGVzdGtleXQ=',
    'cipher'   => 'AES-256-CBC',
    'timezone' => 'UTC',
    'locale'   => 'en',

    // Default Laravel framework providers + Livewire + our package.
    'providers' => ServiceProvider::defaultProviders()->merge([
        Livewire\LivewireServiceProvider::class,
        ABTests\ABTestingServiceProvider::class,
    ])->toArray(),

    'aliases' => [],
];
