<?php

declare(strict_types=1);

namespace ABTests;

use ABTests\Application\Listeners\AutoPauseOnGuardrailBreachListener;
use ABTests\Application\ResultsService;
use ABTests\Presentation\Middleware\ResolveExperimentMiddleware;
use ABTests\Application\SynchronousCommandBus;
use ABTests\Console\CacheDefinitionsCommand;
use ABTests\Contracts\AssignmentRepository;
use ABTests\Contracts\BucketingStrategy;
use ABTests\Contracts\CommandBus;
use ABTests\Contracts\EventSink;
use ABTests\Contracts\ExperimentStateRepository;
use ABTests\Domain\Events\GuardrailBreachedEvent;
use ABTests\Infrastructure\AlwaysRunningExperimentStateRepository;
use ABTests\Infrastructure\Database\DatabaseAssignmentRepository;
use ABTests\Infrastructure\Database\DatabaseEventSink;
use ABTests\Infrastructure\Database\DatabaseExperimentStateRepository;
use ABTests\Infrastructure\InMemoryAssignmentRepository;
use ABTests\Infrastructure\Jobs\RefreshRollupsJob;
use ABTests\Infrastructure\NullEventSink;
use ABTests\Registry\AttributeReader;
use ABTests\Registry\ClassDiscovery;
use ABTests\Registry\ExperimentRegistry;
use ABTests\Registry\FeatureFlagRegistry;
use ABTests\Resolution\Resolver;
use ABTests\Resolution\Steps\BucketStep;
use ABTests\Resolution\Steps\CheckExperimentActiveStep;
use ABTests\Resolution\Steps\CheckLayerExclusionStep;
use ABTests\Resolution\Steps\CheckSegmentStep;
use ABTests\Resolution\Steps\CheckTrafficAllocationStep;
use ABTests\Resolution\Steps\LoadExistingAssignmentStep;
use ABTests\Resolution\Steps\PersistAssignmentStep;
use ABTests\Statistics\AnalysisService;
use ABTests\Statistics\BayesianAnalysisEngine;
use ABTests\Statistics\FrequentistAnalysisEngine;
use ABTests\Statistics\SampleRatioMismatchDetector;
use ABTests\Statistics\VerdictResolver;
use ABTests\Strategies\Sha256BucketingStrategy;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Wires the framework into the Laravel container. Binds all contracts to their
 * default implementations and populates the ExperimentRegistry from the
 * explicitly configured class list.
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
 *   ResultsService            → ResultsService (TTL-cached)
 */
final class ABTestingServiceProvider extends ServiceProvider
{
    /**
     * @throws BindingResolutionException
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            path: __DIR__ . '/../config/ab-testing.php',
            key: 'ab-testing',
        );

        $this->app->singleton(BucketingStrategy::class, Sha256BucketingStrategy::class);

        $this->bindStorageDriver();

        $this->app->singleton(ExperimentRegistry::class, function (): ExperimentRegistry {
            $registry = new ExperimentRegistry();
            $reader = new AttributeReader();

            /** @var list<class-string<Experiment>> $classes */
            $classes = $this->app->make(ConfigRepository::class)->get('ab-testing.experiments', []);

            foreach ($classes as $class) {
                try {
                    $definition = $reader->readExperiment($class);
                    $registry->register($definition, $class);
                } catch (Throwable $e) {
                    // Log rather than crash at boot time; the ab:cache command
                    // surfaces individual failures with better context.
                    if ($this->app->bound(LoggerInterface::class)) {
                        $this->app->make(LoggerInterface::class)->warning(
                            "[ABTesting] Failed to register experiment [$class]: {$e->getMessage()}"
                        );
                    }
                }
            }

            return $registry;
        });

        $this->app->singleton(FeatureFlagRegistry::class, function (): FeatureFlagRegistry {
            $registry = new FeatureFlagRegistry();
            $reader = new AttributeReader();

            /** @var list<class-string<\ABTests\FeatureFlag>> $classes */
            $classes = $this->app->make(ConfigRepository::class)->get('ab-testing.feature_flags', []);

            foreach ($classes as $class) {
                try {
                    $definition = $reader->readFeatureFlag($class);
                    $registry->register($definition, $class);
                } catch (Throwable $e) {
                    if ($this->app->bound(LoggerInterface::class)) {
                        $this->app->make(LoggerInterface::class)->warning(
                            "[ABTesting] Failed to register feature flag [$class]: {$e->getMessage()}"
                        );
                    }
                }
            }

            return $registry;
        });

        $this->app->singleton(Resolver::class, function (): Resolver {
            $assignmentRepository = $this->app->make(AssignmentRepository::class);

            return new Resolver(
                bucketingStrategy: $this->app->make(BucketingStrategy::class),
                stateRepository: $this->app->make(ExperimentStateRepository::class),
                steps: [
                    new CheckExperimentActiveStep(),
                    new CheckSegmentStep(),
                    new CheckTrafficAllocationStep(),
                    new LoadExistingAssignmentStep($assignmentRepository),
                    new CheckLayerExclusionStep($assignmentRepository),
                    new BucketStep(),
                    new PersistAssignmentStep($assignmentRepository),
                ],
            );
        });

        $this->app->singleton(FrequentistAnalysisEngine::class, FrequentistAnalysisEngine::class);
        $this->app->singleton(BayesianAnalysisEngine::class, BayesianAnalysisEngine::class);
        $this->app->singleton(SampleRatioMismatchDetector::class, SampleRatioMismatchDetector::class);
        $this->app->singleton(VerdictResolver::class, VerdictResolver::class);

        $this->app->singleton(AnalysisService::class, function (): AnalysisService {
            return new AnalysisService(
                frequentistEngine: $this->app->make(FrequentistAnalysisEngine::class),
                bayesianEngine: $this->app->make(BayesianAnalysisEngine::class),
                srmDetector: $this->app->make(SampleRatioMismatchDetector::class),
                verdictResolver: $this->app->make(VerdictResolver::class),
            );
        });

        $this->app->singleton(ResultsService::class, function (): ResultsService {
            return new ResultsService(
                registry: $this->app->make(ExperimentRegistry::class),
                analysisService: $this->app->make(AnalysisService::class),
            );
        });

        $this->app->singleton(
            CommandBus::class,
            fn (): SynchronousCommandBus => new SynchronousCommandBus($this->app),
        );

        $this->app->singleton(
            Experiments::class,
            fn (): Experiments => new Experiments(
                registry: $this->app->make(ExperimentRegistry::class),
                flagRegistry: $this->app->make(FeatureFlagRegistry::class),
                resolver: $this->app->make(Resolver::class),
                eventSink: $this->app->make(EventSink::class),
                assignmentRepository: $this->app->make(AssignmentRepository::class),
                bucketingStrategy: $this->app->make(BucketingStrategy::class),
            ),
        );
    }

    /**
     * If auto-discovery is enabled, scan the configured paths and register any
     * Experiment or FeatureFlag subclass found into the appropriate registry.
     * Runs after the singleton factories have already populated the explicit
     * 'experiments' and 'feature_flags' lists, so discovered classes are
     * additive — duplicates are silently skipped by the registries.
     *
     * @throws BindingResolutionException
     */
    private function bootDiscovery(): void
    {
        /** @var array<string, mixed> $config */
        $config = $this->app->make(ConfigRepository::class)->get('ab-testing', []);

        if (! ($config['discovery']['enabled'] ?? false)) {
            return;
        }

        /** @var list<string> $paths */
        $paths = $config['discovery']['paths'] ?? [];

        if ($paths === []) {
            return;
        }

        $discovered = new ClassDiscovery()->discover($paths);

        if ($discovered === []) {
            return;
        }

        $reader           = new AttributeReader();
        $experimentRegistry = $this->app->make(ExperimentRegistry::class);
        $flagRegistry       = $this->app->make(FeatureFlagRegistry::class);

        foreach ($discovered as $class) {
            if (! class_exists($class)) {
                continue;
            }

            if (is_a($class, Experiment::class, true)) {
                try {
                    $definition = $reader->readExperiment($class);
                    $experimentRegistry->register($definition, $class);
                } catch (Throwable $e) {
                    if ($this->app->bound(LoggerInterface::class)) {
                        $this->app->make(LoggerInterface::class)->warning(
                            "[ABTesting] Discovery: failed to register experiment [$class]: {$e->getMessage()}"
                        );
                    }
                }

                continue;
            }

            if (is_a($class, FeatureFlag::class, true)) {
                try {
                    $definition = $reader->readFeatureFlag($class);
                    $flagRegistry->register($definition, $class);
                } catch (Throwable $e) {
                    if ($this->app->bound(LoggerInterface::class)) {
                        $this->app->make(LoggerInterface::class)->warning(
                            "[ABTesting] Discovery: failed to register feature flag [$class]: {$e->getMessage()}"
                        );
                    }
                }
            }
        }
    }

    /**
     * Bind the storage driver contracts to their implementations. 'database' is
     * the production default; 'in_memory' is suitable for tests and local
     * development without a database.
     *
     * @throws BindingResolutionException
     */
    private function bindStorageDriver(): void
    {
        /** @var string $driver */
        $driver = $this->app->make(ConfigRepository::class)->get('ab-testing.storage.driver', 'database');

        if ($driver === 'in_memory') {
            $this->app->singleton(AssignmentRepository::class, InMemoryAssignmentRepository::class);
            $this->app->singleton(ExperimentStateRepository::class, AlwaysRunningExperimentStateRepository::class);
            $this->app->singleton(EventSink::class, NullEventSink::class);

            return;
        }

        // 'database' driver — Eloquent implementations backed by PostgreSQL/MySQL.
        $this->app->singleton(AssignmentRepository::class, DatabaseAssignmentRepository::class);
        $this->app->singleton(ExperimentStateRepository::class, DatabaseExperimentStateRepository::class);
        $this->app->singleton(EventSink::class, DatabaseEventSink::class);
    }

    /**
     * @throws BindingResolutionException
     */
    public function boot(): void
    {
        Experiments::setInstance($this->app->make(Experiments::class));

        $this->bootDiscovery();

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'ab-testing');
        $this->loadRoutesFrom(__DIR__ . '/Dashboard/routes.php');

        // Register anonymous Blade components under the ab-testing:: prefix so
        // <x-ab-testing::status-badge> and <x-ab-testing::verdict-badge> resolve
        // to resources/views/components/*.blade.php.
        Blade::anonymousComponentPath(__DIR__ . '/../resources/views/components', 'ab-testing');

        // Register the ab-testing Livewire namespace so the :: resolver can
        // auto-discover all components in ABTests\Dashboard\Livewire, including
        // experiments-overview, experiment-detail, experiment-controls, and
        // experiment-results-table, without individual registrations.
        if (class_exists(Livewire::class)) {
            Livewire::addNamespace('ab-testing', classNamespace: 'ABTests\\Dashboard\\Livewire');
        }

        // Register the variant-resolution middleware alias so consumers can use
        // ->middleware('ab-testing.resolve') on routes with #[ResolvesExperiment].
        if ($this->app->bound(\Illuminate\Routing\Router::class)) {
            $this->app->make(\Illuminate\Routing\Router::class)
                ->aliasMiddleware('ab-testing.resolve', ResolveExperimentMiddleware::class);
        }

        // Guardrail breach → auto-pause listener.
        Event::listen(GuardrailBreachedEvent::class, AutoPauseOnGuardrailBreachListener::class);

        // Flush the DatabaseEventSink buffer at the end of every request.
        /** @var string $driver */
        $driver = $this->app->make(ConfigRepository::class)->get('ab-testing.storage.driver', 'database');

        if ($driver === 'database') {
            $this->app->terminating(function (): void {
                /** @var DatabaseEventSink $sink */
                $sink = $this->app->make(EventSink::class);
                $sink->flush();
            });
        }

        // Optional scheduler registration for the rollup job.
        if ($this->app->make(ConfigRepository::class)->get('ab-testing.dashboard.auto_schedule_rollups', true)) {
            $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
                $schedule->job(RefreshRollupsJob::class)->everyFiveMinutes();
            });
        }

        if ($this->app->runningInConsole()) {
            $this->commands([CacheDefinitionsCommand::class]);

            $this->publishes([
                __DIR__ . '/../config/ab-testing.php' => $this->app->configPath('ab-testing.php'),
            ], 'ab-testing-config');

            $this->publishes([
                __DIR__ . '/../database/migrations' => $this->app->databasePath('migrations'),
            ], 'ab-testing-migrations');

            $this->publishes([
                __DIR__ . '/../resources/views' => $this->app->resourcePath('views/vendor/ab-testing'),
            ], 'ab-testing-dashboard-views');
        }

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
