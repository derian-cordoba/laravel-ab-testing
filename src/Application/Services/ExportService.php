<?php

declare(strict_types=1);

namespace ABTests\Application\Services;

use ABTests\Exceptions\ExperimentNotFound;
use ABTests\Infrastructure\Database\Models\ExperimentModel;
use ABTests\Infrastructure\Database\Models\RollupModel;
use Illuminate\Support\Facades\DB;

/**
 * Produces downloadable exports for a single experiment. Two formats:
 *
 *  - JSON: the rollup summary (pre-aggregated sufficient statistics per variant
 *    and metric) — suitable for archival and re-analysis in external tools.
 *
 *  - CSV: the raw event stream for the experiment — suitable for statistical
 *    software that needs per-event granularity. Events are streamed in batches
 *    of 5 000 rows to keep memory usage flat regardless of experiment size.
 *
 * Neither format includes PII beyond the unit_key as it was stored.
 */
final readonly class ExportService
{
    /**
     * Build the JSON rollup export for one experiment.
     * Returns an array that can be json_encoded by the caller.
     *
     * @return array<string, mixed>
     */
    public function rollupAsJson(string $experimentKey): array
    {
        $model = ExperimentModel::query()->firstWhere('key', $experimentKey);

        if ($model === null) {
            throw new ExperimentNotFound($experimentKey);
        }

        $rollups = RollupModel::query()
            ->where('experiment_key', $experimentKey)
            ->orderBy('variant_key')
            ->orderBy('metric_key')
            ->get()
            ->map(static fn (RollupModel $r): array => [
                'variant_key'             => $r->variant_key,
                'metric_key'              => $r->metric_key,
                'count_of_units'          => $r->count_of_units,
                'exposures'               => $r->exposures,
                'conversions'             => $r->conversions,
                'sum_of_values'           => $r->sum_of_values,
                'sum_of_squared_values'   => $r->sum_of_squared_values,
                'updated_through_at'      => $r->updated_through_at,
            ])
            ->all();

        return [
            'experiment_key' => $experimentKey,
            'exported_at'    => now()->toIso8601String(),
            'status'         => $model->status,
            'rollups'        => $rollups,
        ];
    }

    /**
     * Stream the raw event CSV for one experiment to the given file handle or
     * memory buffer. Yields one row per event; the caller controls the output.
     *
     * @param resource $handle  A writable stream (e.g. php://output or fopen(...)).
     * @param int      $chunkSize Events per DB query batch.
     */
    public function streamEventsCsv(string $experimentKey, mixed $handle, int $chunkSize = 5000): void
    {
        if (ExperimentModel::query()->where('key', $experimentKey)->doesntExist()) {
            throw new ExperimentNotFound($experimentKey);
        }

        // Write CSV header.
        fputcsv($handle, [
            'id',
            'experiment_key',
            'unit_type',
            'unit_key',
            'variant_key',
            'type',
            'metric_key',
            'value',
            'idempotency_key',
            'occurred_at',
        ]);

        DB::table('ab_testing_events')
            ->where('experiment_key', $experimentKey)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->chunk($chunkSize, static function ($events) use ($handle): void {
                foreach ($events as $event) {
                    fputcsv($handle, [
                        $event->id,
                        $event->experiment_key,
                        $event->unit_type,
                        $event->unit_key,
                        $event->variant_key,
                        $event->type,
                        $event->metric_key ?? '',
                        $event->value ?? '',
                        $event->idempotency_key,
                        $event->occurred_at,
                    ]);
                }
            });
    }

    /**
     * Return the event count for an experiment (useful for size estimation before
     * triggering a potentially large CSV download).
     */
    public function eventCount(string $experimentKey): int
    {
        return DB::table('ab_testing_events')
            ->where('experiment_key', $experimentKey)
            ->count();
    }
}
