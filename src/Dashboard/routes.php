<?php

declare(strict_types=1);

use ABTests\Dashboard\Http\Middleware\RequiresDashboardAccess;
use ABTests\Dashboard\Livewire\CreateExperiment;
use ABTests\Dashboard\Livewire\CreateFeatureFlag;
use ABTests\Dashboard\Livewire\DashboardIndex;
use ABTests\Dashboard\Livewire\ExperimentDetail;
use ABTests\Dashboard\Livewire\ExperimentsOverview;
use ABTests\Dashboard\Livewire\FeatureFlagDetail;
use ABTests\Dashboard\Livewire\FeatureFlagsOverview;
use ABTests\Http\Controllers\AssignmentsController;
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
        Route::get('/', DashboardIndex::class)->name('index');
        Route::get('/experiments', ExperimentsOverview::class)->name('experiments.index');
        Route::get('/experiments/create', CreateExperiment::class)->name('experiments.create');
        Route::get('/experiments/{key}', ExperimentDetail::class)->name('experiments.show');
        Route::get('/feature-flags', FeatureFlagsOverview::class)->name('feature-flags.index');
        Route::get('/feature-flags/create', CreateFeatureFlag::class)->name('feature-flags.create');
        Route::get('/feature-flags/{key}', FeatureFlagDetail::class)->name('feature-flag.show');

        // Assignment exposure endpoint — allows front-end JS to read server-resolved
        // assignments for a given unit without re-hashing.
        if (config('ab-testing.api.v1.endpoints.assignments.enabled', true)) {
            $assignmentMiddleware = [
                ...config('ab-testing.dashboard.middleware', ['web']),
                ...config('ab-testing.api.v1.middleware', []),
            ];

            $endpointPath = config('ab-testing.api.v1.endpoints.assignments.path', 'assignments');

            Route::middleware($assignmentMiddleware)
                ->get("/$endpointPath", AssignmentsController::class)
                ->name('assignments');
        }
    });
