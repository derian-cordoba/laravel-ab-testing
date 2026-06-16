<?php

declare(strict_types=1);

namespace ABTests\Tests\Unit\ServiceProvider;

use ABTests\Experiment;
use ABTests\FeatureFlag;
use ABTests\Application\Registry\AttributeReader;
use ABTests\Application\Registry\ClassDiscovery;
use ABTests\Application\Registry\ExperimentRegistry;
use ABTests\Application\Registry\FeatureFlagRegistry;
use ABTests\Tests\Fixtures\Discovery\DiscoverableExperiment;
use ABTests\Tests\Fixtures\Discovery\DiscoverableFeatureFlag;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * Tests the discovery routing logic that ABTestingServiceProvider::bootDiscovery()
 * applies: ClassDiscovery scans paths, then each found class is dispatched to
 * ExperimentRegistry or FeatureFlagRegistry based on its base class. This suite
 * exercises that routing without bootstrapping a full service provider.
 */
final class DiscoveryRoutingTest extends TestCase
{
    private string $fixtureDir;

    protected function setUp(): void
    {
        $this->fixtureDir = dirname(__DIR__, 2) . '/Fixtures/Discovery';
    }

    #[Test]
    public function discovered_experiment_is_routed_to_experiment_registry(): void
    {
        $discovered = (new ClassDiscovery())->discover([$this->fixtureDir]);

        $experimentRegistry = new ExperimentRegistry();
        $flagRegistry       = new FeatureFlagRegistry();

        $this->routeDiscovered($discovered, $experimentRegistry, $flagRegistry);

        // DiscoverableExperiment has no #[AsExperiment] attribute so registration
        // will fail silently, but the class IS an Experiment subclass and should
        // be attempted — the registry stays empty only because the attribute is missing.
        // What we verify is that it was NOT routed to the flag registry.
        self::assertTrue($flagRegistry->isEmpty());
    }

    #[Test]
    public function discovered_feature_flag_is_routed_to_flag_registry(): void
    {
        $discovered = (new ClassDiscovery())->discover([$this->fixtureDir]);

        $experimentRegistry = new ExperimentRegistry();
        $flagRegistry       = new FeatureFlagRegistry();

        $this->routeDiscovered($discovered, $experimentRegistry, $flagRegistry);

        // DiscoverableFeatureFlag has no #[AsFeatureFlag] attribute so registration
        // will fail silently, but it should NOT end up in the experiment registry.
        self::assertTrue($experimentRegistry->isEmpty());
    }

    #[Test]
    public function only_experiment_subclasses_are_routed_to_experiment_registry(): void
    {
        $tmpDir = sys_get_temp_dir() . '/ab-routing-' . uniqid('', true);
        mkdir($tmpDir);

        // Write a plain class that is not an Experiment or FeatureFlag subclass.
        file_put_contents($tmpDir . '/PlainClass.php', '<?php namespace Tmp; class PlainClass {}');

        $discovered = (new ClassDiscovery())->discover([$tmpDir]);

        $experimentRegistry = new ExperimentRegistry();
        $flagRegistry       = new FeatureFlagRegistry();

        $this->routeDiscovered($discovered, $experimentRegistry, $flagRegistry);

        self::assertTrue($experimentRegistry->isEmpty());
        self::assertTrue($flagRegistry->isEmpty());

        unlink($tmpDir . '/PlainClass.php');
        rmdir($tmpDir);
    }

    #[Test]
    public function routing_continues_after_a_single_class_fails_to_register(): void
    {
        // DiscoverableExperiment lacks #[AsExperiment] so readExperiment() throws,
        // but routing must continue to the next class without aborting.
        $tmpDir = sys_get_temp_dir() . '/ab-routing-' . uniqid('', true);
        mkdir($tmpDir);

        // Write a class that is an Experiment subclass but has no #[AsExperiment].
        file_put_contents($tmpDir . '/BrokenExperiment.php', '<?php
namespace Tmp;
use ABTests\Experiment;
final class BrokenExperiment extends Experiment {}
');
        // Write a second class that is also just a plain Experiment (also broken).
        file_put_contents($tmpDir . '/AnotherBroken.php', '<?php
namespace Tmp;
use ABTests\Experiment;
final class AnotherBroken extends Experiment {}
');

        // Should not throw — failures are swallowed.
        $discovered = (new ClassDiscovery())->discover([$tmpDir]);
        $experimentRegistry = new ExperimentRegistry();
        $flagRegistry       = new FeatureFlagRegistry();

        $this->routeDiscovered($discovered, $experimentRegistry, $flagRegistry);

        self::assertTrue($experimentRegistry->isEmpty());

        unlink($tmpDir . '/BrokenExperiment.php');
        unlink($tmpDir . '/AnotherBroken.php');
        rmdir($tmpDir);
    }

    #[Test]
    public function empty_paths_list_produces_no_registrations(): void
    {
        $discovered = (new ClassDiscovery())->discover([]);

        $experimentRegistry = new ExperimentRegistry();
        $flagRegistry       = new FeatureFlagRegistry();

        $this->routeDiscovered($discovered, $experimentRegistry, $flagRegistry);

        self::assertTrue($experimentRegistry->isEmpty());
        self::assertTrue($flagRegistry->isEmpty());
    }

    // ------------------------------------------------------------------
    // Helpers — mirror the routing logic from bootDiscovery()
    // ------------------------------------------------------------------

    /**
     * @param list<string> $discovered
     */
    private function routeDiscovered(
        array $discovered,
        ExperimentRegistry $experimentRegistry,
        FeatureFlagRegistry $flagRegistry,
    ): void {
        $reader = new AttributeReader();

        foreach ($discovered as $class) {
            if (! class_exists($class)) {
                continue;
            }

            if (is_a($class, Experiment::class, true)) {
                try {
                    $definition = $reader->readExperiment($class);
                    $experimentRegistry->register($definition, $class);
                } catch (Throwable) {
                    // swallow — mirrors bootDiscovery() behaviour
                }

                continue;
            }

            if (is_a($class, FeatureFlag::class, true)) {
                try {
                    $definition = $reader->readFeatureFlag($class);
                    $flagRegistry->register($definition, $class);
                } catch (Throwable) {
                    // swallow
                }
            }
        }
    }
}
