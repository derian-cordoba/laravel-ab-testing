<?php

declare(strict_types=1);

namespace ABTests\Tests\Integration;

use Illuminate\Database\Capsule\Manager as DB;
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

        $capsule = new DB();
        $capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
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
    }

    private function dropSchema(): void
    {
        DB::schema()->dropIfExists('ab_testing_assignments');
        DB::schema()->dropIfExists('ab_testing_experiments');
    }
}
