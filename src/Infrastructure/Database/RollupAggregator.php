<?php

declare(strict_types=1);

namespace ABTests\Infrastructure\Database;

use ABTests\Enums\EventType;
use ABTests\Infrastructure\Database\Models\RollupModel;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Infrastructure service that processes raw event rows and incrementally
 * upserts rollup aggregate rows in the database.
 *
 * Responsibilities extracted from RefreshRollupsJob:
 *   - Watermark resolution (oldest updated_through_at across rollup rows)
 *   - Event chunking and delta accumulation
 *   - Insert-or-update rollup rows (incremental upsert pattern)
 *   - count_of_units full-recount (v1 simplification)
 */
final class RollupAggregator
{
    /**
     * Process events newer than the current watermark for the given experiment
     * and upsert the corresponding rollup rows. Returns the latest occurred_at
     * timestamp processed, or null if no new events were found.
     *
     * @param  list<string>  $metricKeys
     * @param  array<string, true>  $ratioMetricKeys  Keys whose MetricType is Ratio
     */
    public function aggregate(string $experimentKey, array $metricKeys, array $ratioMetricKeys): ?string
    {
        if ($metricKeys === []) {
            return null;
        }

        $watermark = RollupModel::query()
            ->where('experiment_key', $experimentKey)
            ->min('updated_through_at');

        $query = DB::table('ab_testing_events')
            ->where('experiment_key', $experimentKey)
            ->when($watermark !== null, static fn ($q) => $q->where('occurred_at', '>', $watermark));

        $latestOccurredAt = null;

        $query->orderBy('occurred_at')->chunk(5000, function ($events) use ($experimentKey, $metricKeys, $ratioMetricKeys, &$latestOccurredAt): void {
            $this->processChunk($events, $experimentKey, $metricKeys, $ratioMetricKeys, $latestOccurredAt);
        });

        if ($latestOccurredAt !== null) {
            $this->recountUnits($experimentKey);
        }

        return $latestOccurredAt;
    }

    /**
     * @param  iterable<object>  $events
     * @param  list<string>  $metricKeys
     * @param  array<string, true>  $ratioMetricKeys
     */
    private function processChunk(
        iterable $events,
        string $experimentKey,
        array $metricKeys,
        array $ratioMetricKeys,
        mixed &$latestOccurredAt,
    ): void {
        $deltas = [];

        foreach ($events as $event) {
            $variantKey = $event->variant_key;
            $type = EventType::from($event->type);
            $occurredAt = $event->occurred_at;

            if ($latestOccurredAt === null || $occurredAt > $latestOccurredAt) {
                $latestOccurredAt = $occurredAt;
            }

            foreach ($metricKeys as $metricKey) {
                $groupKey = "$variantKey|$metricKey";
                $isRatio = isset($ratioMetricKeys[$metricKey]);

                $deltas[$groupKey] ??= [
                    'variant_key' => $variantKey,
                    'metric_key' => $metricKey,
                    'is_ratio' => $isRatio,
                    'exposures_delta' => 0,
                    'sum_of_values_delta' => 0.0,
                    'sum_of_squared_values_delta' => 0.0,
                    'conversions_delta' => 0,
                    'sum_of_denominators_delta' => 0.0,
                    'sum_of_squared_denominators_delta' => 0.0,
                    'sum_of_numerator_denominator_delta' => 0.0,
                ];

                if ($type === EventType::exposure) {
                    $deltas[$groupKey]['exposures_delta']++;
                }

                if ($type === EventType::conversion || $type === EventType::metric) {
                    $eventMetricKey = $event->metric_key ?? null;
                    if ($eventMetricKey !== null && $eventMetricKey !== $metricKey) {
                        continue;
                    }

                    $value = (float) ($event->value ?? 1.0);
                    $deltas[$groupKey]['conversions_delta']++;
                    $deltas[$groupKey]['sum_of_values_delta'] += $value;
                    $deltas[$groupKey]['sum_of_squared_values_delta'] += $value * $value;

                    if ($isRatio) {
                        $properties = is_string($event->properties ?? null)
                            ? (array) json_decode($event->properties, true)
                            : [];
                        $denominator = isset($properties['denominator'])
                            ? (float) $properties['denominator']
                            : 1.0;

                        $deltas[$groupKey]['sum_of_denominators_delta'] += $denominator;
                        $deltas[$groupKey]['sum_of_squared_denominators_delta'] += $denominator * $denominator;
                        $deltas[$groupKey]['sum_of_numerator_denominator_delta'] += $value * $denominator;
                    }
                }
            }
        }

        foreach ($deltas as $delta) {
            $isRatio = $delta['is_ratio'];

            $insertRow = [
                'experiment_key' => $experimentKey,
                'variant_key' => $delta['variant_key'],
                'metric_key' => $delta['metric_key'],
                'exposures' => $delta['exposures_delta'],
                'sum_of_values' => $delta['sum_of_values_delta'],
                'sum_of_squared_values' => $delta['sum_of_squared_values_delta'],
                'conversions' => $delta['conversions_delta'],
                'updated_through_at' => $latestOccurredAt,
                'updated_at' => Carbon::now(),
            ];

            if ($isRatio) {
                $insertRow['sum_of_denominators'] = $delta['sum_of_denominators_delta'];
                $insertRow['sum_of_squared_denominators'] = $delta['sum_of_squared_denominators_delta'];
                $insertRow['sum_of_numerator_denominator'] = $delta['sum_of_numerator_denominator_delta'];
            }

            $inserted = DB::table('ab_testing_rollups')->insertOrIgnore($insertRow);

            if ($inserted === 0) {
                $updateFields = [
                    'exposures' => DB::raw("exposures + {$delta['exposures_delta']}"),
                    'sum_of_values' => DB::raw("sum_of_values + {$delta['sum_of_values_delta']}"),
                    'sum_of_squared_values' => DB::raw("sum_of_squared_values + {$delta['sum_of_squared_values_delta']}"),
                    'conversions' => DB::raw("conversions + {$delta['conversions_delta']}"),
                    'updated_through_at' => $latestOccurredAt,
                    'updated_at' => Carbon::now(),
                ];

                if ($isRatio) {
                    $updateFields['sum_of_denominators'] = DB::raw(
                        "COALESCE(sum_of_denominators, 0) + {$delta['sum_of_denominators_delta']}",
                    );
                    $updateFields['sum_of_squared_denominators'] = DB::raw(
                        "COALESCE(sum_of_squared_denominators, 0) + {$delta['sum_of_squared_denominators_delta']}",
                    );
                    $updateFields['sum_of_numerator_denominator'] = DB::raw(
                        "COALESCE(sum_of_numerator_denominator, 0) + {$delta['sum_of_numerator_denominator_delta']}",
                    );
                }

                DB::table('ab_testing_rollups')
                    ->where('experiment_key', $experimentKey)
                    ->where('variant_key', $delta['variant_key'])
                    ->where('metric_key', $delta['metric_key'])
                    ->update($updateFields);
            }
        }
    }

    private function recountUnits(string $experimentKey): void
    {
        $counts = DB::table('ab_testing_events')
            ->select('variant_key', DB::raw('COUNT(DISTINCT unit_key) as unit_count'))
            ->where('experiment_key', $experimentKey)
            ->where('type', EventType::exposure->value)
            ->groupBy('variant_key')
            ->get();

        foreach ($counts as $count) {
            RollupModel::query()
                ->where('experiment_key', $experimentKey)
                ->where('variant_key', $count->variant_key)
                ->update(['count_of_units' => $count->unit_count]);
        }
    }
}
