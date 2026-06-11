<?php

declare(strict_types=1);

namespace ABTests\Tests\Integration\Experiments;

use ABTests\Contracts\AssignmentRepository;
use ABTests\Contracts\Bucketable;
use ABTests\Contracts\BucketingStrategy;
use ABTests\Contracts\EventSink;
use ABTests\Contracts\ExperimentStateRepository;
use ABTests\Experiments;
use ABTests\Infrastructure\AlwaysRunningExperimentStateRepository;
use ABTests\Infrastructure\Database\Models\FeatureFlagStateModel;
use ABTests\Infrastructure\InMemoryAssignmentRepository;
use ABTests\Infrastructure\NullEventSink;
use ABTests\Registry\AttributeReader;
use ABTests\Registry\ExperimentRegistry;
use ABTests\Registry\FeatureFlagRegistry;
use ABTests\Resolution\Resolver;
use ABTests\Tests\Fixtures\TestFeatureFlag;
use ABTests\Tests\Fixtures\TestUnit;
use ABTests\Tests\Integration\DatabaseTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Integration tests for Experiments::flag() / resolveFlag() — the full flag
 * resolution pipeline including kill switch, conditions, rollout gating, and
 * the final resolve() call, all backed by an in-memory SQLite database.
 */
final class FlagResolutionTest extends DatabaseTestCase
{
    private FeatureFlagRegistry $flagRegistry;

    protected function setUp(): void
    {
        parent::setUp();

        $reader             = new AttributeReader();
        $this->flagRegistry = new FeatureFlagRegistry();

        $definition = $reader->readFeatureFlag(TestFeatureFlag::class);
        $this->flagRegistry->register($definition, TestFeatureFlag::class);

        Experiments::setInstance($this->makeExperiments());
    }

    // ------------------------------------------------------------------
    // No DB state → default value
    // ------------------------------------------------------------------

    #[Test]
    public function returns_default_value_when_no_database_record_exists(): void
    {
        // No FeatureFlagStateModel row exists.
        $result = Experiments::flag(TestFeatureFlag::class, new TestUnit('user-1'));

        self::assertFalse($result);
    }

    // ------------------------------------------------------------------
    // Disabled state
    // ------------------------------------------------------------------

    #[Test]
    public function returns_default_value_when_flag_is_disabled(): void
    {
        FeatureFlagStateModel::query()->create([
            'key'                => 'test-flag',
            'is_enabled'         => false,
            'rollout_percentage' => 100,
        ]);

        $result = Experiments::flag(TestFeatureFlag::class, new TestUnit('user-1'));

        self::assertFalse($result);
    }

    // ------------------------------------------------------------------
    // Kill switch
    // ------------------------------------------------------------------

    #[Test]
    public function returns_default_value_when_kill_switch_is_active(): void
    {
        FeatureFlagStateModel::query()->create([
            'key'                => 'test-flag',
            'is_enabled'         => true,
            'rollout_percentage' => 100,
            'killed_at'          => \Carbon\Carbon::now(),
        ]);

        $result = Experiments::flag(TestFeatureFlag::class, new TestUnit('user-1'));

        self::assertFalse($result);
    }

    #[Test]
    public function does_not_short_circuit_when_kill_switch_is_inactive(): void
    {
        FeatureFlagStateModel::query()->create([
            'key'                => 'test-flag',
            'is_enabled'         => true,
            'rollout_percentage' => 100,
            'killed_at'          => null,
        ]);

        // With a position of 0.0 (always in rollout) the flag resolves to true
        // via TestFeatureFlag::resolve() which always returns true.
        $result = $this->resolveWithPosition(0.0);

        self::assertTrue($result);
    }

    // ------------------------------------------------------------------
    // Rollout gating
    // ------------------------------------------------------------------

    #[Test]
    public function returns_default_when_unit_position_is_at_or_above_rollout_threshold(): void
    {
        FeatureFlagStateModel::query()->create([
            'key'                => 'test-flag',
            'is_enabled'         => true,
            'rollout_percentage' => 50,
        ]);

        // position 0.50 >= 0.50 → outside rollout
        $result = $this->resolveWithPosition(0.50);

        self::assertFalse($result);
    }

    #[Test]
    public function resolves_flag_when_unit_position_is_below_rollout_threshold(): void
    {
        FeatureFlagStateModel::query()->create([
            'key'                => 'test-flag',
            'is_enabled'         => true,
            'rollout_percentage' => 50,
        ]);

        // position 0.49 < 0.50 → inside rollout
        $result = $this->resolveWithPosition(0.49);

        self::assertTrue($result);
    }

    #[Test]
    public function zero_percent_rollout_excludes_all_units(): void
    {
        FeatureFlagStateModel::query()->create([
            'key'                => 'test-flag',
            'is_enabled'         => true,
            'rollout_percentage' => 0,
        ]);

        $result = $this->resolveWithPosition(0.0);

        self::assertFalse($result);
    }

    #[Test]
    public function full_100_percent_rollout_includes_all_units(): void
    {
        FeatureFlagStateModel::query()->create([
            'key'                => 'test-flag',
            'is_enabled'         => true,
            'rollout_percentage' => 100,
        ]);

        $result = $this->resolveWithPosition(0.9999);

        self::assertTrue($result);
    }

    // ------------------------------------------------------------------
    // DB conditions (unitMatchesConditions)
    // ------------------------------------------------------------------

    #[Test]
    public function resolves_flag_when_no_conditions_are_set(): void
    {
        FeatureFlagStateModel::query()->create([
            'key'                => 'test-flag',
            'is_enabled'         => true,
            'rollout_percentage' => 100,
            'conditions'         => null,
        ]);

        $result = $this->resolveWithPosition(0.0);

        self::assertTrue($result);
    }

    #[Test]
    public function returns_default_when_unit_does_not_match_conditions(): void
    {
        FeatureFlagStateModel::query()->create([
            'key'                => 'test-flag',
            'is_enabled'         => true,
            'rollout_percentage' => 100,
            'conditions'         => [
                ['attribute' => 'plan', 'operator' => 'equals', 'expected' => 'pro'],
            ],
        ]);

        // TestUnit has plan=free by default
        $result = Experiments::flag(TestFeatureFlag::class, new TestUnit('user-1', ['plan' => 'free']));

        self::assertFalse($result);
    }

    #[Test]
    public function resolves_flag_when_unit_matches_all_conditions(): void
    {
        FeatureFlagStateModel::query()->create([
            'key'                => 'test-flag',
            'is_enabled'         => true,
            'rollout_percentage' => 100,
            'conditions'         => [
                ['attribute' => 'plan', 'operator' => 'equals', 'expected' => 'pro'],
            ],
        ]);

        $result = Experiments::flag(TestFeatureFlag::class, new TestUnit('user-1', ['plan' => 'pro']));

        self::assertTrue($result);
    }

    #[Test]
    public function all_conditions_must_match_and_logic(): void
    {
        FeatureFlagStateModel::query()->create([
            'key'                => 'test-flag',
            'is_enabled'         => true,
            'rollout_percentage' => 100,
            'conditions'         => [
                ['attribute' => 'plan',    'operator' => 'equals', 'expected' => 'pro'],
                ['attribute' => 'country', 'operator' => 'equals', 'expected' => 'US'],
            ],
        ]);

        // plan matches but country doesn't → off
        $miss = Experiments::flag(TestFeatureFlag::class, new TestUnit('user-1', ['plan' => 'pro', 'country' => 'GB']));
        self::assertFalse($miss);

        // both match → on (position 0.0 is always inside 100% rollout)
        $hit = Experiments::flag(TestFeatureFlag::class, new TestUnit('user-2', ['plan' => 'pro', 'country' => 'US']));
        self::assertTrue($hit);
    }

    #[Test]
    public function conditions_support_in_operator(): void
    {
        FeatureFlagStateModel::query()->create([
            'key'                => 'test-flag',
            'is_enabled'         => true,
            'rollout_percentage' => 100,
            'conditions'         => [
                ['attribute' => 'plan', 'operator' => 'in', 'expected' => ['pro', 'enterprise']],
            ],
        ]);

        $free       = Experiments::flag(TestFeatureFlag::class, new TestUnit('u1', ['plan' => 'free']));
        $pro        = Experiments::flag(TestFeatureFlag::class, new TestUnit('u2', ['plan' => 'pro']));
        $enterprise = Experiments::flag(TestFeatureFlag::class, new TestUnit('u3', ['plan' => 'enterprise']));

        self::assertFalse($free);
        self::assertTrue($pro);
        self::assertTrue($enterprise);
    }

    // ------------------------------------------------------------------
    // Unregistered flag → reads default from attribute
    // ------------------------------------------------------------------

    #[Test]
    public function returns_attribute_default_value_for_unregistered_flag(): void
    {
        // TestFeatureFlag has defaultValue: false in its #[AsFeatureFlag] attribute.
        // Build an Experiments instance WITHOUT registering the flag.
        $emptyFlagRegistry = new FeatureFlagRegistry();

        Experiments::setInstance(new Experiments(
            registry: new ExperimentRegistry(),
            flagRegistry: $emptyFlagRegistry,
            resolver: $this->makeResolver(),
            eventSink: new NullEventSink(),
            assignmentRepository: new InMemoryAssignmentRepository(),
            bucketingStrategy: $this->fixedPosition(0.0),
        ));

        $result = Experiments::flag(TestFeatureFlag::class, new TestUnit('user-1'));

        self::assertFalse($result);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Resolve TestFeatureFlag with a fixed bucketing position, using an
     * enabled DB record with 100% rollout (so the only gating is the position).
     */
    private function resolveWithPosition(float $position): mixed
    {
        Experiments::setInstance(new Experiments(
            registry: new ExperimentRegistry(),
            flagRegistry: $this->flagRegistry,
            resolver: $this->makeResolver(),
            eventSink: new NullEventSink(),
            assignmentRepository: new InMemoryAssignmentRepository(),
            bucketingStrategy: $this->fixedPosition($position),
        ));

        return Experiments::flag(TestFeatureFlag::class, new TestUnit('user-1'));
    }

    private function makeExperiments(): Experiments
    {
        return new Experiments(
            registry: new ExperimentRegistry(),
            flagRegistry: $this->flagRegistry,
            resolver: $this->makeResolver(),
            eventSink: new NullEventSink(),
            assignmentRepository: new InMemoryAssignmentRepository(),
            bucketingStrategy: $this->fixedPosition(0.0),
        );
    }

    private function makeResolver(): Resolver
    {
        return new Resolver(
            bucketingStrategy: $this->fixedPosition(0.0),
            stateRepository: new AlwaysRunningExperimentStateRepository(),
            steps: [],
        );
    }

    private function fixedPosition(float $position): BucketingStrategy
    {
        return new readonly class ($position) implements BucketingStrategy {
            public function __construct(private float $position) {}

            public function position(string $salt, Bucketable $unit): float
            {
                return $this->position;
            }
        };
    }
}
