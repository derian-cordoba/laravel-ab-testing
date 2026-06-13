<?php

declare(strict_types=1);

use ABTests\Http\Controllers\Api\AssignmentsController;
use ABTests\Http\Controllers\Api\ExperimentLifecycleController;
use ABTests\Http\Controllers\Api\ExperimentResultsController;
use ABTests\Http\Controllers\Api\ExperimentsController;
use ABTests\Http\Controllers\Api\VariantsController;
use ABTests\Http\Middleware\ApiExceptionHandlerMiddleware;
use ABTests\Http\Middleware\EnforceAcceptHeaderMiddleware;
use ABTests\Http\Middleware\RequiresApiAccess;
use ABTests\Http\Middleware\SetApiContentTypeMiddleware;
use Illuminate\Support\Facades\Route;

$prefix = config('ab-testing.api.v1.endpoints.experiments.prefix', 'api/v1/ab-testing');

$middleware = [
    ...config('ab-testing.api.v1.middleware', ['api']),
    SetApiContentTypeMiddleware::class,        // outermost — stamps Content-Type on all responses
    EnforceAcceptHeaderMiddleware::class,       // rejects requests with wrong/missing Accept header
    ApiExceptionHandlerMiddleware::class,       // converts exceptions to JSON:API errors documents
    RequiresApiAccess::class,                  // gate-based access control
];

Route::prefix($prefix)
    ->middleware($middleware)
    ->name('ab-testing.api.v1.')
    ->group(function (): void {
        if (config('ab-testing.api.v1.endpoints.assignments.enabled', true)) {
            $path = config('ab-testing.api.v1.endpoints.assignments.path', 'assignments');

            Route::get("/$path", AssignmentsController::class)->name('assignments');
        }

        if (config('ab-testing.api.v1.endpoints.experiments.enabled', true)) {
            Route::prefix('/experiments')->as('experiments.')->group(static function () {
                Route::get('/', [ExperimentsController::class, 'index'])->name('index');
                Route::post('/', [ExperimentsController::class, 'store'])->name('store');
                Route::get('/{key}', [ExperimentsController::class, 'show'])->name('show');
                Route::put('/{key}', [ExperimentsController::class, 'update'])->name('update');
                Route::delete('/{key}', [ExperimentsController::class, 'destroy'])->name('destroy');

                Route::post('/{key}/start', [ExperimentLifecycleController::class, 'start'])->name('start');
                Route::post('/{key}/pause', [ExperimentLifecycleController::class, 'pause'])->name('pause');
                Route::post('/{key}/resume', [ExperimentLifecycleController::class, 'resume'])->name('resume');
                Route::post('/{key}/stop', [ExperimentLifecycleController::class, 'stop'])->name('stop');
                Route::post('/{key}/traffic', [ExperimentLifecycleController::class, 'rampTraffic'])->name('traffic');
                Route::post('/{key}/kill-switch', [ExperimentLifecycleController::class, 'killSwitch'])->name('kill-switch');
                Route::post('/{key}/kill-switch/deactivate', [ExperimentLifecycleController::class, 'deactivateKillSwitch'])->name('kill-switch.deactivate');

                Route::get('/{key}/results', [ExperimentResultsController::class, 'show'])->name('results');
                Route::get('/{key}/verdict', [ExperimentResultsController::class, 'verdict'])->name('verdict');

                Route::post('/{key}/variants', [VariantsController::class, 'store'])->name('variants.store');
                Route::put('/{key}/variants/{id}', [VariantsController::class, 'update'])->name('variants.update');
                Route::delete('/{key}/variants/{id}', [VariantsController::class, 'destroy'])->name('variants.destroy');
            });
        }
    });
