<?php

declare(strict_types=1);

namespace ABTests\Providers;

use ABTests\Infrastructure\Jobs\PruneEventDataJob;
use ABTests\Infrastructure\Jobs\RefreshRollupsJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the package's background scheduler entries.
 *
 *   RefreshRollupsJob  — incremental rollup aggregation, every 5 minutes
 *   PruneEventDataJob  — event data retention, every Sunday at midnight
 *
 * Both are opt-out via config:
 *   ab-testing.dashboard.auto_schedule_rollups  (default: true)
 *   ab-testing.retention.auto_schedule          (default: true)
 */
final class SchedulingServiceProvider extends ServiceProvider
{
    /**
     * @throws BindingResolutionException
     */
    public function boot(): void
    {
        /** @var array<string, mixed> $config */
        $config = $this->app->make(ConfigRepository::class)->get('ab-testing', []);

        if ($config['dashboard']['auto_schedule_rollups'] ?? true) {
            $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
                $schedule->job(RefreshRollupsJob::class)->everyFiveMinutes();
            });
        }

        if ($config['retention']['auto_schedule'] ?? true) {
            $this->callAfterResolving(Schedule::class, static function (Schedule $schedule): void {
                $schedule->job(PruneEventDataJob::class)->weekly()->sundays()->atMidnight();
            });
        }
    }
}
