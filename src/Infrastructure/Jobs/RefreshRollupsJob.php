<?php

declare(strict_types=1);

namespace ABTests\Infrastructure\Jobs;

use ABTests\Definitions\MetricBinding;
use ABTests\Domain\Events\GuardrailBreachedEvent;
use ABTests\Enums\EventType;
use ABTests\Enums\ExperimentStatus;
use ABTests\Enums\MetricType;
use ABTests\Infrastructure\Database\Models\ExperimentModel;
use ABTests\Infrastructure\Database\Models\GuardrailBreachModel;
use ABTests\Infrastructure\Database\Models\RollupModel;
use ABTests\Application\Registry\AttributeReader;
use ABTests\Application\Registry\ExperimentRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * Watermarked incremental rollup job. Processes events newer than the last
 * updated_through_at watermark for each active experiment, upserts rollup rows,
 * and checks guardrail thresholds after each cycle.
 *
 * count_of_units is computed via COUNT(DISTINCT unit_key) — a full recount
 * acceptable for v1 scales. See DASHBOARD.md §Open Questions for the v2 path.
 */
final class RefreshRollupsJob implements ShouldQueue
{
    use Queueable;

    public function handle(ExperimentRegistry $registry): void
    {
        $activeStatuses = [ExperimentStatus::running->value, ExperimentStatus::paused->value];

        ExperimentModel::query()
            ->whereIn('status', $activeStatuses)
            ->each(function (ExperimentModel $experiment) use ($registry): void {
                $this->refreshExperiment($experiment, $registry);
            });
    }

    /**
     * Refresh rollups for one specific experiment, regardless of lifecycle
     * status. This is used by the dashboard's manual "Refresh Data" control,
     * which should work for completed experiments too.
     */
    public function refreshExperimentByKey(string $experimentKey, ExperimentRegistry $registry): bool
    {
        $experiment = ExperimentModel::query()->firstWhere('key', $experimentKey);

        if ($experiment === null) {
            return false;
        }

        $this->refreshExperiment($experiment, $registry);

        return true;
    }

    private function refreshExperiment(ExperimentModel $experiment, ExperimentRegistry $registry): void
    {
        // Resolve the code-defined experiment definition to get metric keys.
        try {
            $definition = $registry->findByKey($experiment->key);
        } catch (Throwable) {
            // Experiment is in the DB but not yet in the registry (runtime-defined).
            // Skip until the registry supports runtime metrics (v2).
            return;
        }

        $metricKeys = array_values(array_unique(array_map(
            static fn (MetricBinding $binding): string => $binding->metric,
            $definition->metrics,
        )));

        if ($metricKeys === []) {
            return;
        }

        // Build a set of metric keys whose MetricType is Ratio so processEventChunk
        // knows which metrics need the extra delta-method sufficient statistics.
        $ratioMetricKeys = $this->resolveRatioMetricKeys($definition->metrics, $registry, $experiment->key);

        // Determine watermark: the oldest updated_through_at across all rollup
        // rows for this experiment, or null if none exist yet.
        $watermark = RollupModel::query()
            ->where('experiment_key', $experiment->key)
            ->min('updated_through_at');

        $query = DB::table('ab_testing_events')
            ->where('experiment_key', $experiment->key)
            ->when($watermark !== null, static fn ($query) => $query->where('occurred_at', '>', $watermark));

        $latestOccurredAt = null;

        $query->orderBy('occurred_at')->chunk(5000, function ($events) use ($experiment, $metricKeys, $ratioMetricKeys, &$latestOccurredAt): void {
            $this->processEventChunk($events, $experiment->key, $metricKeys, $ratioMetricKeys, $latestOccurredAt);
        });

        if ($latestOccurredAt !== null) {
            // Refresh count_of_units via full recount (v1 simplification).
            $this->recountUnits($experiment->key);

            $this->checkGuardrails(
                experimentKey: $experiment->key,
                guardrails: $definition->guardrails(),
                controlVariantKey: $definition->allocation->control()->key(),
            );
        }
    }

    /**
     * @param iterable<object> $events
     * @param list<string>     $metricKeys
     * @param array<string, true> $ratioMetricKeys Keys that are MetricType::Ratio
     */
    private function processEventChunk(
        iterable $events,
        string $experimentKey,
        array $metricKeys,
        array $ratioMetricKeys,
        mixed &$latestOccurredAt,
    ): void {
        // Accumulate deltas grouped by (variant_key, metric_key).
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
                $isRatio  = isset($ratioMetricKeys[$metricKey]);

                $deltas[$groupKey] ??= [
                    'variant_key' => $variantKey,
                    'metric_key' => $metricKey,
                    'is_ratio' => $isRatio,
                    'exposures_delta' => 0,
                    'sum_of_values_delta' => 0.0,
                    'sum_of_squared_values_delta' => 0.0,
                    'conversions_delta' => 0,
                    // Ratio-specific accumulators.
                    'sum_of_denominators_delta' => 0.0,
                    'sum_of_squared_denominators_delta' => 0.0,
                    'sum_of_numerator_denominator_delta' => 0.0,
                ];

                if ($type === EventType::exposure) {
                    $deltas[$groupKey]['exposures_delta']++;
                }

                if ($type === EventType::conversion || $type === EventType::metric) {
                    // Skip metric events that belong to a different metric key so
                    // each rollup bucket only counts its own events.
                    $eventMetricKey = $event->metric_key ?? null;
                    if ($eventMetricKey !== null && $eventMetricKey !== $metricKey) {
                        continue;
                    }

                    $value = (float) ($event->value ?? 1.0);
                    $deltas[$groupKey]['conversions_delta']++;
                    $deltas[$groupKey]['sum_of_values_delta'] += $value;
                    $deltas[$groupKey]['sum_of_squared_values_delta'] += $value * $value;

                    // For ratio metrics the event properties carry a 'denominator'
                    // field. The numerator is the event value. When no denominator
                    // is present (e.g. a plain conversion), we default to 1.
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
                'experiment_key'       => $experimentKey,
                'variant_key'          => $delta['variant_key'],
                'metric_key'           => $delta['metric_key'],
                'exposures'            => $delta['exposures_delta'],
                'sum_of_values'        => $delta['sum_of_values_delta'],
                'sum_of_squared_values' => $delta['sum_of_squared_values_delta'],
                'conversions'          => $delta['conversions_delta'],
                'updated_through_at'   => $latestOccurredAt,
                'updated_at'           => Carbon::now(),
            ];

            if ($isRatio) {
                $insertRow['sum_of_denominators']         = $delta['sum_of_denominators_delta'];
                $insertRow['sum_of_squared_denominators'] = $delta['sum_of_squared_denominators_delta'];
                $insertRow['sum_of_numerator_denominator'] = $delta['sum_of_numerator_denominator_delta'];
            }

            // insertOrIgnore creates the row on first run. On subsequent runs the
            // UNIQUE constraint fires and 0 rows are inserted, which is the signal
            // to ADD the deltas to the existing row instead. DB::raw increment
            // expressions are only valid in UPDATE statements, not in INSERT.
            $inserted = DB::table('ab_testing_rollups')->insertOrIgnore($insertRow);

            if ($inserted === 0) {
                $updateFields = [
                    'exposures'            => DB::raw("exposures + {$delta['exposures_delta']}"),
                    'sum_of_values'        => DB::raw("sum_of_values + {$delta['sum_of_values_delta']}"),
                    'sum_of_squared_values' => DB::raw("sum_of_squared_values + {$delta['sum_of_squared_values_delta']}"),
                    'conversions'          => DB::raw("conversions + {$delta['conversions_delta']}"),
                    'updated_through_at'   => $latestOccurredAt,
                    'updated_at'           => Carbon::now(),
                ];

                if ($isRatio) {
                    $updateFields['sum_of_denominators'] = DB::raw(
                        "COALESCE(sum_of_denominators, 0) + {$delta['sum_of_denominators_delta']}"
                    );
                    $updateFields['sum_of_squared_denominators'] = DB::raw(
                        "COALESCE(sum_of_squared_denominators, 0) + {$delta['sum_of_squared_denominators_delta']}"
                    );
                    $updateFields['sum_of_numerator_denominator'] = DB::raw(
                        "COALESCE(sum_of_numerator_denominator, 0) + {$delta['sum_of_numerator_denominator_delta']}"
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

    /**
     * Build a lookup of metric keys whose type is MetricType::Ratio.
     * Used to decide whether to populate the delta-method sufficient statistics
     * columns in the rollup table.
     *
     * @param list<MetricBinding> $metrics
     * @param ExperimentRegistry  $registry
     * @param string              $experimentKey
     * @return array<string, true>
     */
    private function resolveRatioMetricKeys(array $metrics, ExperimentRegistry $registry, string $experimentKey): array
    {
        // Find the original experiment class so we can read #[AsMetric] types.
        $experimentClass = $registry->findClassByKey($experimentKey);

        if ($experimentClass === null) {
            return []; // Runtime-defined experiment — no code attributes to read.
        }

        try {
            $reader = new AttributeReader();
            $metricTypes = $reader->readMetricTypes($experimentClass);

            $ratioKeys = [];
            foreach ($metricTypes as $key => $type) {
                if ($type === MetricType::ratio) {
                    $ratioKeys[$key] = true;
                }
            }

            return $ratioKeys;
        } catch (Throwable) {
            return [];
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

    /**
     * @param list<MetricBinding> $guardrails
     */
    private function checkGuardrails(string $experimentKey, array $guardrails, string $controlVariantKey): void
    {
        foreach ($guardrails as $guardrail) {
            $rollups = RollupModel::query()
                ->where('experiment_key', $experimentKey)
                ->where('metric_key', $guardrail->metric)
                ->get();

            $controlRollup = $rollups->first(
                static fn (RollupModel $rollup): bool => $rollup->variant_key === $controlVariantKey
            );

            foreach ($rollups as $rollup) {
                if (
                    $rollup->variant_key === $controlVariantKey
                    || $rollup->count_of_units === 0
                    || $controlRollup === null
                ) {
                    continue;
                }

                $controlRate = $controlRollup->count_of_units > 0
                    ? $controlRollup->conversions / $controlRollup->count_of_units
                    : 0.0;

                $treatmentRate = $rollup->count_of_units > 0
                    ? $rollup->conversions / $rollup->count_of_units
                    : 0.0;

                if ($controlRate === 0.0) {
                    continue;
                }

                $relativeRegression = ($controlRate - $treatmentRate) / $controlRate;
                $maximumRegression = $guardrail->maximumRegression ?? 0.0;

                if ($relativeRegression > $maximumRegression) {
                    $alreadyBreached = GuardrailBreachModel::query()
                        ->where('experiment_key', $experimentKey)
                        ->where('metric_key', $guardrail->metric)
                        ->where('variant_key', $rollup->variant_key)
                        ->where('is_acknowledged', false)
                        ->exists();

                    if (! $alreadyBreached) {
                        GuardrailBreachModel::query()->create([
                            'experiment_key' => $experimentKey,
                            'metric_key' => $guardrail->metric,
                            'variant_key' => $rollup->variant_key,
                            'observed_value' => $relativeRegression,
                            'threshold_value' => $maximumRegression,
                            'breached_at' => Carbon::now(),
                        ]);

                        Event::dispatch(new GuardrailBreachedEvent(
                            experimentKey: $experimentKey,
                            metricKey: $guardrail->metric,
                            variantKey: $rollup->variant_key,
                            observedValue: $relativeRegression,
                            thresholdValue: $maximumRegression,
                        ));
                    }
                }
            }
        }
    }
}
