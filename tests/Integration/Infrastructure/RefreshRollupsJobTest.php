<?php

declare(strict_types=1);

namespace ABTests\Tests\Integration\Infrastructure;

use ABTests\Infrastructure\Database\Models\ExperimentModel;
use ABTests\Infrastructure\Database\Models\RollupModel;
use ABTests\Infrastructure\Jobs\RefreshRollupsJob;
use ABTests\Application\Registry\AttributeReader;
use ABTests\Application\Registry\ExperimentRegistry;
use ABTests\Tests\Fixtures\TestExperiment;
use ABTests\Tests\Integration\DatabaseTestCase;
use Illuminate\Database\Capsule\Manager as DB;
use PHPUnit\Framework\Attributes\Test;

final class RefreshRollupsJobTest extends DatabaseTestCase
{
    #[Test]
    public function manual_refresh_can_rebuild_rollups_for_a_completed_experiment(): void
    {
        ExperimentModel::query()->create([
            'key' => 'test-experiment',
            'status' => 'completed',
            'traffic_percentage' => 100,
            'is_killed' => false,
        ]);

        DB::table('ab_testing_events')->insert([
            [
                'experiment_key' => 'test-experiment',
                'unit_type' => 'test-user',
                'unit_key' => 'user-1',
                'variant_key' => 'control',
                'type' => 'exposure',
                'metric_key' => null,
                'value' => null,
                'properties' => null,
                'idempotency_key' => 'exp-1',
                'occurred_at' => '2026-06-11 10:00:00',
            ],
            [
                'experiment_key' => 'test-experiment',
                'unit_type' => 'test-user',
                'unit_key' => 'user-1',
                'variant_key' => 'control',
                'type' => 'metric',
                'metric_key' => 'test-conversion',
                'value' => 1.0,
                'properties' => null,
                'idempotency_key' => 'metric-1',
                'occurred_at' => '2026-06-11 10:05:00',
            ],
        ]);

        $registry = new ExperimentRegistry();
        $registry->register((new AttributeReader())->readExperiment(TestExperiment::class), TestExperiment::class);

        $refreshed = (new RefreshRollupsJob())->refreshExperimentByKey('test-experiment', $registry);

        self::assertTrue($refreshed);

        $rollup = RollupModel::query()
            ->where('experiment_key', 'test-experiment')
            ->where('variant_key', 'control')
            ->where('metric_key', 'test-conversion')
            ->first();

        self::assertNotNull($rollup);
        self::assertSame(1, $rollup->exposures);
        self::assertSame(1, $rollup->conversions);
        self::assertEqualsWithDelta(1.0, $rollup->sum_of_values, 1e-10);
        self::assertSame(1, $rollup->count_of_units);
    }
}
