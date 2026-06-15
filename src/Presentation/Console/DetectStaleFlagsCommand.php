<?php

declare(strict_types=1);

namespace ABTests\Presentation\Console;

use ABTests\Infrastructure\Database\Models\FeatureFlagStateModel;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * php artisan ab:detect-stale-flags
 *
 * Lists every enabled feature flag whose last_evaluated_at (or, as a fallback,
 * updated_at) is older than the configured stale_threshold_days. Killed and
 * disabled flags are not reported — they are already in a decided state.
 *
 * Exit codes:
 *   0 — no stale flags found
 *   1 — one or more stale flags detected (useful for CI alerting)
 */
final class DetectStaleFlagsCommand extends Command
{
    protected $signature = 'ab:detect-stale-flags
        {--days= : Override the stale threshold (days). Defaults to flags.stale_threshold_days config.}
        {--format=table : Output format: table or json}';

    protected $description = 'Report feature flags that have not been evaluated within the stale threshold.';

    public function handle(): int
    {
        $thresholdDays = $this->option('days') !== null
            ? (int) $this->option('days')
            : (int) config('ab-testing.feature_flags.stale_threshold_days', 90);

        if ($thresholdDays <= 0) {
            $this->info('Stale-flag detection is disabled (stale_threshold_days = 0).');

            return self::SUCCESS;
        }

        $cutoff = Carbon::now()->subDays($thresholdDays);

        $staleFlags = FeatureFlagStateModel::query()
            ->where('is_enabled', true)
            ->whereNull('killed_at')
            ->where(static function ($query) use ($cutoff): void {
                // Prefer last_evaluated_at; fall back to updated_at when the
                // column is present but null (flag never evaluated since migration).
                $query->where(static function ($q) use ($cutoff): void {
                    $q->whereNotNull('last_evaluated_at')
                      ->where('last_evaluated_at', '<', $cutoff);
                })->orWhere(static function ($q) use ($cutoff): void {
                    $q->whereNull('last_evaluated_at')
                      ->where('updated_at', '<', $cutoff);
                });
            })
            ->orderBy('key')
            ->get(['key', 'rollout_percentage', 'last_evaluated_at', 'updated_at']);

        if ($staleFlags->isEmpty()) {
            $this->info("No stale flags found (threshold: {$thresholdDays} days).");

            return self::SUCCESS;
        }

        $format = $this->option('format');

        if ($format === 'json') {
            $this->line($staleFlags->toJson(JSON_PRETTY_PRINT));

            return self::FAILURE;
        }

        $rows = $staleFlags->map(static function (FeatureFlagStateModel $flag): array {
            $lastActivity = $flag->last_evaluated_at ?? $flag->updated_at;
            $daysStale = $lastActivity !== null
                ? (int) $lastActivity->diffInDays(Carbon::now())
                : '—';

            return [
                $flag->key,
                $flag->rollout_percentage . '%',
                $lastActivity?->toDateTimeString() ?? '—',
                $daysStale,
            ];
        })->toArray();

        $this->warn("Found {$staleFlags->count()} stale flag(s) (threshold: $thresholdDays days):");
        $this->newLine();
        $this->table(
            ['Key', 'Rollout', 'Last Evaluated', 'Days Stale'],
            $rows,
        );

        return self::FAILURE;
    }
}
