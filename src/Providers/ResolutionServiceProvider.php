<?php

declare(strict_types=1);

namespace ABTests\Providers;

use ABTests\Application\Registry\AttributeReader;
use ABTests\Application\Registry\ClassDiscovery;
use ABTests\Application\Registry\ExperimentRegistry;
use ABTests\Application\Registry\FeatureFlagRegistry;
use ABTests\Application\Resolution\ExposureRecorder;
use ABTests\Application\Resolution\Resolver;
use ABTests\Application\Resolution\Steps\BucketStep;
use ABTests\Application\Resolution\Steps\CheckEnvironmentStep;
use ABTests\Application\Resolution\Steps\CheckExperimentActiveStep;
use ABTests\Application\Resolution\Steps\CheckLayerExclusionStep;
use ABTests\Application\Resolution\Steps\CheckSegmentStep;
use ABTests\Application\Resolution\Steps\CheckTrafficAllocationStep;
use ABTests\Application\Resolution\Steps\LoadExistingAssignmentStep;
use ABTests\Application\Resolution\Steps\PersistAssignmentStep;
use ABTests\Application\SynchronousCommandBus;
use ABTests\Contracts\AssignmentRepository;
use ABTests\Contracts\BucketingStrategy;
use ABTests\Contracts\CommandBus;
use ABTests\Contracts\DomainEventDispatcher;
use ABTests\Contracts\EventSink;
use ABTests\Contracts\ExperimentStateRepository;
use ABTests\Contracts\FeatureFlagRepository;
use ABTests\Domain\Experiment\Experiment;
use ABTests\Domain\FeatureFlag\FeatureFlag;
use ABTests\Experiments;
use ABTests\Infrastructure\Events\LaravelDomainEventDispatcher;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Wires the experiment and feature-flag registries, the resolution pipeline
 * (Resolver + 8 steps), the command bus, and the Experiments facade singleton.
 */
final class ResolutionServiceProvider extends ServiceProvider
{
    /**
     * @throws BindingResolutionException
     */
    public function register(): void
    {
        $this->registerExperimentRegistry();
        $this->registerFeatureFlagRegistry();
        $this->registerResolver();

        $this->app->singleton(
            DomainEventDispatcher::class,
            LaravelDomainEventDispatcher::class,
        );

        $this->app->singleton(
            CommandBus::class,
            fn (): SynchronousCommandBus => new SynchronousCommandBus($this->app),
        );

        $this->app->singleton(ExposureRecorder::class, function (): ExposureRecorder {
            return new ExposureRecorder($this->app->make(EventSink::class));
        });

        $this->app->singleton(
            Experiments::class,
            fn (): Experiments => new Experiments(
                registry: $this->app->make(ExperimentRegistry::class),
                flagRegistry: $this->app->make(FeatureFlagRegistry::class),
                resolver: $this->app->make(Resolver::class),
                exposureRecorder: $this->app->make(ExposureRecorder::class),
                eventSink: $this->app->make(EventSink::class),
                assignmentRepository: $this->app->make(AssignmentRepository::class),
                bucketingStrategy: $this->app->make(BucketingStrategy::class),
                featureFlagRepository: $this->app->make(FeatureFlagRepository::class),
                stateRepository: $this->app->make(ExperimentStateRepository::class),
                commandBus: $this->app->make(CommandBus::class),
            ),
        );
    }

    /**
     * @throws BindingResolutionException
     */
    public function boot(): void
    {
        $this->bootDiscovery();
    }

    private function registerExperimentRegistry(): void
    {
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
                    if ($this->app->bound(LoggerInterface::class)) {
                        $this->app->make(LoggerInterface::class)->warning(
                            "[ABTesting] Failed to register experiment [$class]: {$e->getMessage()}",
                        );
                    }
                }
            }

            return $registry;
        });
    }

    private function registerFeatureFlagRegistry(): void
    {
        $this->app->singleton(FeatureFlagRegistry::class, function (): FeatureFlagRegistry {
            $registry = new FeatureFlagRegistry();
            $reader = new AttributeReader();

            /** @var list<class-string<FeatureFlag>> $classes */
            $classes = $this->app->make(ConfigRepository::class)->get('ab-testing.feature_flags.register', []);

            foreach ($classes as $class) {
                try {
                    $definition = $reader->readFeatureFlag($class);
                    $registry->register($definition, $class);
                } catch (Throwable $e) {
                    if ($this->app->bound(LoggerInterface::class)) {
                        $this->app->make(LoggerInterface::class)->warning(
                            "[ABTesting] Failed to register feature flag [$class]: {$e->getMessage()}",
                        );
                    }
                }
            }

            return $registry;
        });
    }

    private function registerResolver(): void
    {
        $this->app->singleton(Resolver::class, function (): Resolver {
            $assignmentRepository = $this->app->make(AssignmentRepository::class);

            return new Resolver(
                bucketingStrategy: $this->app->make(BucketingStrategy::class),
                stateRepository: $this->app->make(ExperimentStateRepository::class),
                steps: [
                    new CheckExperimentActiveStep(),
                    new CheckEnvironmentStep(),
                    new CheckSegmentStep(),
                    new CheckTrafficAllocationStep(),
                    new LoadExistingAssignmentStep($assignmentRepository),
                    new CheckLayerExclusionStep($assignmentRepository),
                    new BucketStep(),
                    new PersistAssignmentStep($assignmentRepository),
                ],
            );
        });
    }

    /**
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

        $discovered = (new ClassDiscovery())->discover($paths);

        if ($discovered === []) {
            return;
        }

        $reader = new AttributeReader();
        $experimentRegistry = $this->app->make(ExperimentRegistry::class);
        $flagRegistry = $this->app->make(FeatureFlagRegistry::class);

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
                            "[ABTesting] Discovery: failed to register experiment [$class]: {$e->getMessage()}",
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
                            "[ABTesting] Discovery: failed to register feature flag [$class]: {$e->getMessage()}",
                        );
                    }
                }
            }
        }
    }
}
