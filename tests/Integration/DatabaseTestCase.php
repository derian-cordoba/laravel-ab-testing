<?php

declare(strict_types=1);

namespace ABTests\Tests\Integration;

use ABTests\Tests\Support\TestApplication;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Facade;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

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
    protected static ?DB $capsule = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::bootCapsule();
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Unit tests may reset the global container and facade application between
        // test classes. Re-establish them before each integration test so that
        // command handlers calling Event::dispatch() and code calling
        // Container::getInstance() both see the capsule's container.
        $container = self::$capsule->getContainer();
        Container::setInstance($container);
        Facade::setFacadeApplication($container);

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

        $container = new TestApplication();
        Facade::setFacadeApplication($container);

        // Create the dispatcher first so we can bind it directly.
        // Note: $capsule->setEventDispatcher() binds 'events' in the capsule's
        // internal container, but setContainer() replaces that container, which
        // means $capsule->getEventDispatcher() subsequently returns null. Capture
        // the dispatcher here and bind it explicitly to avoid this pitfall.
        $dispatcher = new Dispatcher($container);

        $capsule = new DB();
        $capsule->setEventDispatcher($dispatcher);
        $capsule->addConnection([
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
        $capsule->setContainer($container);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        $container->instance('db', $capsule->getDatabaseManager());
        $container->instance('events', $dispatcher);

        // Register a config repository so global config() calls (e.g. in
        // command handlers) resolve against the package defaults instead of
        // throwing "Call to undefined function config()".
        $container->instance('config', new ConfigRepository([
            'ab-testing' => ['governance' => ['approval_required' => false]],
        ]));

        // Bind a null logger so Log facade calls (e.g. Log::warning()) in
        // command handlers do not throw a BindingResolutionException.
        $container->instance('log', new NullLogger());

        // Set this container as the global IoC instance so that app() and
        // config() helpers resolve against it during integration tests.
        Container::setInstance($container);

        // Enable foreign-key enforcement for SQLite (off by default).
        $capsule->getConnection()->statement('PRAGMA foreign_keys = ON');

        self::$capsule = $capsule;
    }

    private function createSchema(): void
    {
        DB::schema()->create('ab_testing_experiments', static function ($table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('name')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->string('layer')->nullable();
            $table->string('status')->default('draft');
            $table->json('allowed_environments')->nullable();
            $table->unsignedInteger('traffic_percentage')->default(0);
            $table->unsignedInteger('target_sample_size')->nullable();
            $table->boolean('is_killed')->default(false);
            $table->timestamp('killed_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('stopped_at')->nullable();
            $table->timestamps();
        });

        DB::schema()->create('ab_testing_variants', static function ($table): void {
            $table->id();
            $table->unsignedBigInteger('experiment_id');
            $table->string('key');
            $table->unsignedInteger('weight');
            $table->boolean('is_control')->default(false);
            $table->timestamps();

            $table->unique(['experiment_id', 'key']);
            $table->foreign('experiment_id')
                ->references('id')
                ->on('ab_testing_experiments')
                ->cascadeOnDelete();
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

        DB::schema()->create('ab_testing_feature_flag_states', static function ($table): void {
            $table->id();
            $table->string('key')->unique();
            $table->boolean('is_enabled')->default(false);
            $table->json('allowed_environments')->nullable();
            $table->unsignedInteger('rollout_percentage')->default(100);
            $table->json('conditions')->nullable();
            $table->string('conditions_logic')->default('all');
            $table->timestamp('killed_at')->nullable();
            $table->timestamp('last_evaluated_at')->nullable();
            $table->timestamps();
        });
    }

    private function dropSchema(): void
    {
        DB::schema()->dropIfExists('ab_testing_feature_flag_states');
        DB::schema()->dropIfExists('ab_testing_audit_log');
        DB::schema()->dropIfExists('ab_testing_guardrail_breaches');
        DB::schema()->dropIfExists('ab_testing_rollups');
        DB::schema()->dropIfExists('ab_testing_events');
        DB::schema()->dropIfExists('ab_testing_assignments');
        DB::schema()->dropIfExists('ab_testing_variants');
        DB::schema()->dropIfExists('ab_testing_experiments');
    }
}
