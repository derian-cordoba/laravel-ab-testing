<?php

declare(strict_types=1);

namespace ABTests\Presentation\Console;

use ABTests\Infrastructure\Jobs\PruneEventDataJob;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * php artisan ab:prune-events
 *
 * Deletes raw event rows for archived experiments whose events are older than
 * the configured retention period (retention.days). Rollup rows and assignment
 * rows are never deleted.
 *
 * Use --dry-run to see what would be deleted without committing any changes.
 */
final class PruneEventDataCommand extends Command
{
    protected $signature = 'ab:prune-events
        {--days= : Override retention period in days. Defaults to retention.days config.}
        {--experiment= : Prune only the given experiment key.}
        {--dry-run : Show the count of rows that would be deleted without deleting them.}';

    protected $description = 'Delete raw events for archived experiments beyond the retention window.';

    public function handle(PruneEventDataJob $job): int
    {
        $retentionDays = $this->option('days') !== null
            ? (int) $this->option('days')
            : (int) config('ab-testing.retention.days', 365);

        if ($retentionDays <= 0) {
            $this->info('Retention pruning is disabled (retention.days = 0).');

            return self::SUCCESS;
        }

        $cutoff = Carbon::now()->subDays($retentionDays);
        $isDryRun = (bool) $this->option('dry-run');
        $specificKey = $this->option('experiment');

        if ($isDryRun) {
            $this->warn('[dry-run] No rows will be deleted.');
        }

        $this->info("Pruning events older than $retentionDays day(s) (cutoff: {$cutoff->toDateTimeString()}).");

        if ($specificKey) {
            $totalDeleted = $isDryRun
                ? $this->countForExperiment((string) $specificKey, $cutoff)
                : $job->pruneExperiment((string) $specificKey, $cutoff);

            $verb = $isDryRun ? 'would delete' : 'deleted';
            $this->line("  [$specificKey] $verb $totalDeleted row(s).");

            return self::SUCCESS;
        }

        // Run the full job synchronously.
        if (! $isDryRun) {
            $job->handle();
            $this->info('Pruning complete.');
        } else {
            $this->line('(dry-run: skipped full execution — pass --experiment to count a specific key)');
        }

        return self::SUCCESS;
    }

    private function countForExperiment(string $experimentKey, Carbon $cutoff): int
    {
        return DB::table('ab_testing_events')
            ->where('experiment_key', $experimentKey)
            ->where('occurred_at', '<', $cutoff)
            ->count();
    }
}
