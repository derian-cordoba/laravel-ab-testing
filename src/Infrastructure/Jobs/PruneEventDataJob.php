<?php

declare(strict_types=1);

namespace ABTests\Infrastructure\Jobs;

use ABTests\Enums\ExperimentStatus;
use ABTests\Infrastructure\Database\Models\ExperimentModel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Prunes raw event rows for archived experiments whose events are older than
 * the configured retention period. Rollup rows are kept indefinitely — they
 * are tiny and serve as the permanent statistical record.
 *
 * Assignment rows are also retained: they form the structural record of which
 * units were exposed and must be preserved for post-hoc analysis even after the
 * events themselves are pruned.
 *
 * Runs in configurable chunks to avoid large DELETE statements locking the table.
 */
final class PruneEventDataJob implements ShouldQueue
{
    use Queueable;

    private const int CHUNK_SIZE = 10_000;

    public function handle(): void
    {
        $retentionDays = (int) config('ab-testing.retention.days', 365);

        if ($retentionDays <= 0) {
            return;
        }

        $cutoff = Carbon::now()->subDays($retentionDays);

        // Only prune events for experiments that are archived; running/paused
        // experiments keep their full event history.
        $archivedKeys = ExperimentModel::query()
            ->where('status', ExperimentStatus::archived->value)
            ->pluck('key')
            ->all();

        if ($archivedKeys === []) {
            return;
        }

        $totalDeleted = 0;

        foreach ($archivedKeys as $experimentKey) {
            $deleted = $this->pruneExperiment($experimentKey, $cutoff);
            $totalDeleted += $deleted;

            if ($deleted > 0) {
                Log::info("[ABTesting] Pruned $deleted event row(s) for archived experiment [$experimentKey].");
            }
        }

        if ($totalDeleted > 0) {
            Log::info("[ABTesting] PruneEventDataJob complete. Total rows pruned: $totalDeleted.");
        }
    }

    /**
     * Prune events for a single archived experiment in small chunks.
     * Returns the total number of rows deleted.
     */
    public function pruneExperiment(string $experimentKey, Carbon $cutoff): int
    {
        $totalDeleted = 0;

        do {
            $ids = DB::table('ab_testing_events')
                ->where('experiment_key', $experimentKey)
                ->where('occurred_at', '<', $cutoff)
                ->limit(self::CHUNK_SIZE)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $deleted = DB::table('ab_testing_events')
                ->whereIn('id', $ids)
                ->delete();

            $totalDeleted += $deleted;
        } while ($deleted > 0);

        return $totalDeleted;
    }
}
