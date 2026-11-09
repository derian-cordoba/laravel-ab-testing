<?php

declare(strict_types=1);

namespace ABTests\Providers;

use ABTests\Contracts\AssignmentRepository;
use ABTests\Contracts\AuditLogRepository;
use ABTests\Contracts\BucketingStrategy;
use ABTests\Contracts\CovariateRepository;
use ABTests\Contracts\EventSink;
use ABTests\Contracts\ExperimentRepository;
use ABTests\Contracts\ExperimentStateRepository;
use ABTests\Contracts\FeatureFlagRepository;
use ABTests\Governance\Contracts\ApprovalPolicy;
use ABTests\Governance\Database\DatabaseApprovalPolicy;
use ABTests\Infrastructure\AlwaysRunningExperimentStateRepository;
use ABTests\Infrastructure\Bucketing\Sha256BucketingStrategy;
use ABTests\Infrastructure\Database\DatabaseAssignmentRepository;
use ABTests\Infrastructure\Database\DatabaseAuditLogRepository;
use ABTests\Infrastructure\Database\DatabaseCovariateRepository;
use ABTests\Infrastructure\Database\DatabaseEventSink;
use ABTests\Infrastructure\Database\DatabaseExperimentRepository;
use ABTests\Infrastructure\Database\DatabaseExperimentStateRepository;
use ABTests\Infrastructure\Database\DatabaseFeatureFlagRepository;
use ABTests\Infrastructure\InMemoryAssignmentRepository;
use ABTests\Infrastructure\NullEventSink;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\ServiceProvider;

/**
 * Binds all storage-layer contracts to their default implementations.
 *
 * Supports two drivers, selectable via config('ab-testing.storage.driver'):
 *   'database'  — Eloquent / PostgreSQL (default, production-ready)
 *   'in_memory' — request-scoped arrays (tests, local dev without a DB)
 *
 * To swap an individual binding, re-bind the contract in your
 * AppServiceProvider after ABTestingServiceProvider has registered.
 */
final class StorageServiceProvider extends ServiceProvider
{
    /**
     * @throws BindingResolutionException
     */
    public function register(): void
    {
        $this->app->singleton(BucketingStrategy::class, Sha256BucketingStrategy::class);
        $this->app->singleton(CovariateRepository::class, DatabaseCovariateRepository::class);
        $this->app->singleton(ApprovalPolicy::class, DatabaseApprovalPolicy::class);

        $this->bindStorageDriver();
    }

    /**
     * Bind the driver-specific repository implementations.
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
        $this->app->singleton(ExperimentRepository::class, DatabaseExperimentRepository::class);
        $this->app->singleton(FeatureFlagRepository::class, DatabaseFeatureFlagRepository::class);
        $this->app->singleton(AuditLogRepository::class, DatabaseAuditLogRepository::class);
    }

    public function boot(): void
    {
        /** @var string $driver */
        $driver = $this->app->make(ConfigRepository::class)->get('ab-testing.storage.driver', 'database');

        if ($driver === 'database') {
            // Flush the DatabaseEventSink buffer at the end of every request.
            $this->app->terminating(function (): void {
                /** @var DatabaseEventSink $sink */
                $sink = $this->app->make(EventSink::class);
                $sink->flush();
            });
        }
    }
}
