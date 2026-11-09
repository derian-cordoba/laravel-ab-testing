<?php

declare(strict_types=1);

namespace ABTests;

use ABTests\Providers\AnalysisServiceProvider;
use ABTests\Providers\PresentationServiceProvider;
use ABTests\Providers\ResolutionServiceProvider;
use ABTests\Providers\SchedulingServiceProvider;
use ABTests\Providers\StorageServiceProvider;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\ServiceProvider;

/**
 * Package entry point registered in composer.json's extra.laravel.providers.
 *
 * Delegates all wiring to focused sub-providers, each independently
 * overridable in the host application's config/app.php:
 *
 *   StorageServiceProvider      — repository bindings + storage driver selection
 *   AnalysisServiceProvider     — statistical engines, CUPED, SRM, ResultsService
 *   ResolutionServiceProvider   — registries, Resolver + 8 pipeline steps, CommandBus
 *   PresentationServiceProvider — views, Blade, Livewire, middleware, console, notifications
 *   SchedulingServiceProvider   — RefreshRollupsJob and PruneEventDataJob scheduling
 *
 * Default bindings (all swappable via config or by re-binding in AppServiceProvider):
 *
 *   BucketingStrategy         → Sha256BucketingStrategy
 *   AssignmentRepository      → DatabaseAssignmentRepository      (database driver, default)
 *                             → InMemoryAssignmentRepository      (in_memory driver)
 *   ExperimentStateRepository → DatabaseExperimentStateRepository (database driver, default)
 *                             → AlwaysRunningExperimentStateRepository (in_memory driver)
 *   EventSink                 → DatabaseEventSink  (database driver)
 *                             → NullEventSink      (in_memory driver)
 *   CommandBus                → SynchronousCommandBus
 *   ResultsService            → ResultsService (backed by rollups table)
 */
final class ABTestingServiceProvider extends ServiceProvider
{
    /**
     * @throws BindingResolutionException
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            path: __DIR__.'/../config/ab-testing.php',
            key: 'ab-testing',
        );

        $this->app->register(StorageServiceProvider::class);
        $this->app->register(AnalysisServiceProvider::class);
        $this->app->register(ResolutionServiceProvider::class);
        $this->app->register(PresentationServiceProvider::class);
        $this->app->register(SchedulingServiceProvider::class);
    }

    /**
     * @throws BindingResolutionException
     */
    public function boot(): void
    {
        Experiments::setInstance($this->app->make(Experiments::class));
    }
}
