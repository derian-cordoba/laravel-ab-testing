<?php

declare(strict_types=1);

namespace ABTests\Console;

use ABTests\Experiment;
use ABTests\FeatureFlag;
use ABTests\Registry\AttributeReader;
use ABTests\Registry\ClassDiscovery;
use ABTests\Registry\ExperimentRegistry;
use ABTests\Registry\FeatureFlagRegistry;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\BindingResolutionException;
use Throwable;

/**
 * php artisan ab:cache
 *
 * Discovers all code-defined experiments (classes decorated with #[AsExperiment])
 * from the explicitly registered list and/or the configured scan paths, reads
 * their PHP attributes via AttributeReader, and registers them in the runtime
 * ExperimentRegistry.
 *
 * Run this after deploying new experiment definitions so the registry is
 * populated without attribute scanning on every request.
 */
final class CacheDefinitionsCommand extends Command
{
    protected $signature = 'ab:cache';

    protected $description = 'Discover and cache all experiment and feature-flag definitions.';

    /**
     * @throws BindingResolutionException
     */
    public function handle(
        ExperimentRegistry $registry,
        FeatureFlagRegistry $flagRegistry,
        AttributeReader $reader,
    ): int {
        /** @var ConfigRepository $configRepo */
        $configRepo = $this->laravel->make(ConfigRepository::class);

        /** @var array<string, mixed> $config */
        $config = $configRepo->get('ab-testing', []);

        /** @var list<class-string<Experiment>> $explicitExperiments */
        $explicitExperiments = $config['experiments'] ?? [];

        /** @var list<class-string<FeatureFlag>> $explicitFlags */
        $explicitFlags = $config['feature_flags'] ?? [];

        /** @var list<string> $paths */
        $paths = $config['discovery']['paths'] ?? [];

        $discovered = [];

        if (($config['discovery']['enabled'] ?? false) && $paths !== []) {
            $discovered = new ClassDiscovery()->discover($paths);
        }

        $allClasses = array_values(array_unique(array_merge($explicitExperiments, $explicitFlags, $discovered)));

        if ($allClasses === []) {
            $this->warn('No experiment or feature flag classes found. Add them to config/ab-testing.php or enable discovery.');

            return self::SUCCESS;
        }

        $registered = 0;
        $failed = 0;

        foreach ($allClasses as $class) {
            if (! class_exists($class)) {
                $this->error("  Class not found: $class");
                $failed++;
                continue;
            }

            if (is_a($class, Experiment::class, true)) {
                try {
                    $definition = $reader->readExperiment($class);
                    $registry->register($definition, $class);
                    $this->line("  <info>✓</info> [experiment] $definition->key  ($class)");
                    $registered++;
                } catch (Throwable $e) {
                    $this->error("  ✗ $class: {$e->getMessage()}");
                    $failed++;
                }

                continue;
            }

            if (is_a($class, FeatureFlag::class, true)) {
                try {
                    $definition = $reader->readFeatureFlag($class);
                    $flagRegistry->register($definition, $class);
                    $this->line("  <info>✓</info> [flag]       $definition->key  ($class)");
                    $registered++;
                } catch (Throwable $e) {
                    $this->error("  ✗ $class: {$e->getMessage()}");
                    $failed++;
                }

                continue;
            }

            $this->error("  Class [$class] does not extend " . Experiment::class . ' or ' . FeatureFlag::class . '.');
            $failed++;
        }

        $this->newLine();
        $this->info("Registered $registered definition(s)." . ($failed > 0 ? " $failed failed." : ''));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
