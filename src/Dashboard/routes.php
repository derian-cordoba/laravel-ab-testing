<?php

declare(strict_types=1);

use ABTests\Dashboard\Http\Middleware\RequiresDashboardAccess;
use ABTests\Dashboard\Livewire\ExperimentDetail;
use ABTests\Dashboard\Livewire\ExperimentsOverview;
use Illuminate\Support\Facades\Route;

$prefix = config('ab-testing.dashboard.path', 'ab-testing');
$middleware = [
    ...config('ab-testing.dashboard.middleware', ['web']),
    RequiresDashboardAccess::class,
];

Route::prefix($prefix)
    ->middleware($middleware)
    ->name('ab-testing.')
    ->group(function (): void {
        Route::get('/', ExperimentsOverview::class)->name('overview');
        Route::get('/experiments/{key}', ExperimentDetail::class)->name('experiment.show');
    });
