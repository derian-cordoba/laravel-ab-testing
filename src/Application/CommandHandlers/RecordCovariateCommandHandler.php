<?php

declare(strict_types=1);

namespace ABTests\Application\CommandHandlers;

use ABTests\Application\Commands\RecordCovariateCommand;
use ABTests\Infrastructure\Database\Models\CovariateModel;
use Illuminate\Support\Carbon;

/**
 * Persists a pre-experiment covariate observation. Uses upsert so repeated
 * calls with the same (experiment, metric, unit) triple update the value rather
 * than creating duplicates — this is safe for idempotent pipelines that replay
 * covariate ingestion.
 */
final readonly class RecordCovariateCommandHandler
{
    public function handle(RecordCovariateCommand $command): void
    {
        $recordedAt = $command->recordedAt !== null
            ? Carbon::instance($command->recordedAt)
            : Carbon::now();

        CovariateModel::query()->upsert(
            [
                [
                    'experiment_key' => $command->experimentKey,
                    'metric_key'     => $command->metricKey,
                    'unit_type'      => $command->unitType,
                    'unit_key'       => $command->unitKey,
                    'value'          => $command->value,
                    'recorded_at'    => $recordedAt->toDateTimeString(),
                ],
            ],
            uniqueBy: ['experiment_key', 'metric_key', 'unit_type', 'unit_key'],
            update: ['value', 'recorded_at'],
        );
    }
}
