<?php

declare(strict_types=1);

namespace ABTests;

use ABTests\Console\CacheDefinitionsCommand;
use ABTests\Contracts\AssignmentRepository;
use ABTests\Contracts\BucketingStrategy;
use ABTests\Contracts\EventSink;
use ABTests\Contracts\ExperimentStateRepository;
use ABTests\Infrastructure\AlwaysRunningExperimentStateRepository;
use ABTests\Infrastructure\Database\DatabaseAssignmentRepository;
use ABTests\Infrastructure\Database\DatabaseExperimentStateRepository;
use ABTests\Infrastructure\InMemoryAssignmentRepository;
use ABTests\Infrastructure\NullEventSink;
use ABTests\Registry\AttributeReader;
use ABTests\Registry\ExperimentRegistry;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Psr\Log\LoggerInterface;
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
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\ServiceProvider;
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
 *   EventSink                 → NullEventSink  (replace with queued batch sink)
 */
final class ABTestingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            path: __DIR__ . '/../config/ab-testing.php',
            key: 'ab-testing',
        );

        $this->app->singleton(BucketingStrategy::class, Sha256BucketingStrategy::class);
        $this->app->singleton(EventSink::class, NullEventSink::class);

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

        $this->app->singleton(Experiments::class, function (): Experiments {
            return new Experiments(
                registry: $this->app->make(ExperimentRegistry::class),
                resolver: $this->app->make(Resolver::class),
                eventSink: $this->app->make(EventSink::class),
                assignmentRepository: $this->app->make(AssignmentRepository::class),
            );
        });
    }

    /**
     * Bind the AssignmentRepository and ExperimentStateRepository to either the
     * Eloquent (database) or InMemory implementations based on the configured
     * storage driver. 'database' is the production default; 'in_memory' is
     * used for tests and local development without a database.
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

            return;
        }

        // 'database' driver — Eloquent implementations backed by PostgreSQL/MySQL.
        $this->app->singleton(AssignmentRepository::class, DatabaseAssignmentRepository::class);
        $this->app->singleton(ExperimentStateRepository::class, DatabaseExperimentStateRepository::class);
    }

    /**
     * @throws BindingResolutionException
     */
    public function boot(): void
    {
        Experiments::setInstance($this->app->make(Experiments::class));

        if ($this->app->runningInConsole()) {
            $this->commands([CacheDefinitionsCommand::class]);

            $this->publishes([
                __DIR__ . '/../config/ab-testing.php' => $this->app->configPath('ab-testing.php'),
            ], 'ab-testing-config');

            $this->publishes([
                __DIR__ . '/../database/migrations' => $this->app->databasePath('migrations'),
            ], 'ab-testing-migrations');
        }

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
