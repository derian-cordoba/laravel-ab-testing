<?php

declare(strict_types=1);

namespace ABTests\Tests\Integration;

use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;

/**
 * Base class for integration tests that need a real database. Bootstraps an
 * in-memory SQLite connection via Eloquent's Capsule and runs the package
 * migrations before each test, then tears the schema down after.
 *
 * No Laravel application or service provider is required; this keeps the
 * integration suite fast and free of framework boot overhead.
 */
abstract class DatabaseTestCase extends TestCase
{
    private static ?DB $capsule = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::bootCapsule();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSchema();
    }

    protected function tearDown(): void
    {
        $this->dropSchema();
        parent::tearDown();
    }

    private static function bootCapsule(): void
    {
        if (self::$capsule !== null) {
            return;
        }

        $container = new Container();
        Facade::setFacadeApplication($container);

        $capsule = new DB();
        $capsule->setEventDispatcher(new Dispatcher($container));
        $capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $capsule->setContainer($container);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        $container->instance('db', $capsule->getDatabaseManager());
        $container->instance('events', $capsule->getEventDispatcher());

        self::$capsule = $capsule;
    }

    private function createSchema(): void
    {
        DB::schema()->create('ab_testing_experiments', static function ($table): void {
            $table->id();
            $table->string('key')->unique();
            $table->unsignedInteger('version')->default(1);
            $table->string('layer')->nullable();
            $table->string('status')->default('draft');
            $table->unsignedInteger('traffic_percentage')->default(0);
            $table->unsignedInteger('target_sample_size')->nullable();
            $table->boolean('is_killed')->default(false);
            $table->timestamp('killed_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('stopped_at')->nullable();
            $table->timestamps();
        });

        DB::schema()->create('ab_testing_assignments', static function ($table): void {
            $table->string('experiment_key');
            $table->string('unit_type');
            $table->string('unit_key');
            $table->string('variant_key');
            $table->string('layer')->nullable();
            $table->timestamp('assigned_at');

            $table->primary(['experiment_key', 'unit_type', 'unit_key']);
            $table->index(['layer', 'unit_type', 'unit_key'], 'ab_assignments_layer_unit_index');
        });

        DB::schema()->create('ab_testing_events', static function ($table): void {
            $table->id();
            $table->string('experiment_key');
            $table->string('unit_type');
            $table->string('unit_key');
            $table->string('variant_key');
            $table->string('type');
            $table->string('metric_key')->nullable();
            $table->double('value')->nullable();
            $table->json('properties')->nullable();
            $table->string('idempotency_key')->unique();
            $table->timestamp('occurred_at');
        });

        DB::schema()->create('ab_testing_rollups', static function ($table): void {
            $table->id();
            $table->string('experiment_key');
            $table->string('variant_key');
            $table->string('metric_key');
            $table->unsignedBigInteger('count_of_units')->default(0);
            $table->unsignedBigInteger('exposures')->default(0);
            $table->double('sum_of_values')->default(0.0);
            $table->double('sum_of_squared_values')->default(0.0);
            $table->unsignedBigInteger('conversions')->default(0);
            $table->timestamp('updated_through_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->unique(
                ['experiment_key', 'variant_key', 'metric_key'],
                'ab_rollups_unique_index',
            );
        });

        DB::schema()->create('ab_testing_guardrail_breaches', static function ($table): void {
            $table->id();
            $table->string('experiment_key');
            $table->string('metric_key');
            $table->string('variant_key');
            $table->double('observed_value');
            $table->double('threshold_value');
            $table->timestamp('breached_at');
            $table->boolean('is_acknowledged')->default(false);
            $table->timestamp('acknowledged_at')->nullable();
        });

        DB::schema()->create('ab_testing_audit_log', static function ($table): void {
            $table->id();
            $table->string('actor_identifier')->nullable();
            $table->string('actor_type')->nullable();
            $table->string('action');
            $table->string('experiment_key')->nullable();
            $table->json('before_state')->nullable();
            $table->json('after_state')->nullable();
            $table->timestamp('occurred_at');
        });
    }

    private function dropSchema(): void
    {
        DB::schema()->dropIfExists('ab_testing_audit_log');
        DB::schema()->dropIfExists('ab_testing_guardrail_breaches');
        DB::schema()->dropIfExists('ab_testing_rollups');
        DB::schema()->dropIfExists('ab_testing_events');
        DB::schema()->dropIfExists('ab_testing_assignments');
        DB::schema()->dropIfExists('ab_testing_experiments');
    }
}
