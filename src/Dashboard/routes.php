<?php

declare(strict_types=1);

use ABTests\Presentation\Http\Middleware\RequiresDashboardAccess;
use ABTests\Presentation\Livewire\AuditLog;
use ABTests\Presentation\Livewire\CreateExperiment;
use ABTests\Presentation\Livewire\CreateFeatureFlag;
use ABTests\Presentation\Livewire\DashboardIndex;
use ABTests\Presentation\Livewire\ExperimentDetail;
use ABTests\Presentation\Livewire\ExperimentsOverview;
use ABTests\Presentation\Livewire\FeatureFlagDetail;
use ABTests\Presentation\Livewire\FeatureFlagsOverview;
use ABTests\Presentation\Livewire\LayersMap;
use ABTests\Presentation\Livewire\MetricsCatalog;
use ABTests\Presentation\Livewire\QaOverrides;
use ABTests\Presentation\Livewire\SegmentsOverview;
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
        Route::get('/audit-log', AuditLog::class)->name('audit-log');
        Route::get('/qa-overrides', QaOverrides::class)->name('qa-overrides');
        Route::get('/layers', LayersMap::class)->name('layers');
        Route::get('/metrics', MetricsCatalog::class)->name('metrics');
        Route::get('/segments', SegmentsOverview::class)->name('segments');
    });
