<?php

declare(strict_types=1);

namespace ABTests\Infrastructure\Database;

use ABTests\Contracts\CovariateRepository;
use Illuminate\Support\Facades\DB;

final readonly class DatabaseCovariateRepository implements CovariateRepository
{
    public function hasAny(string $experimentKey, string $metricKey): bool
    {
        return DB::table('ab_testing_covariates')
            ->where('experiment_key', $experimentKey)
            ->where('metric_key', $metricKey)
            ->exists();
    }

    /**
     * @return array<string, array{mean: float, variance: float, cov_yx: float, n: int}>
     */
    public function loadStatsPerVariant(string $experimentKey, string $metricKey): array
    {
        $rows = DB::table('ab_testing_covariates as c')
            ->join('ab_testing_assignments as a', static function ($join) use ($experimentKey): void {
                $join->on('c.unit_type', '=', 'a.unit_type')
                    ->on('c.unit_key', '=', 'a.unit_key')
                    ->where('a.experiment_key', '=', $experimentKey);
            })
            ->join('ab_testing_rollups as r', static function ($join) use ($experimentKey, $metricKey): void {
                $join->on('a.variant_key', '=', 'r.variant_key')
                    ->where('r.experiment_key', '=', $experimentKey)
                    ->where('r.metric_key', '=', $metricKey);
            })
            ->where('c.experiment_key', $experimentKey)
            ->where('c.metric_key', $metricKey)
            ->select(
                'a.variant_key',
                DB::raw('COUNT(*) as n'),
                DB::raw('AVG(c.value) as cov_mean'),
                DB::raw('VAR_SAMP(c.value) as cov_variance'),
                DB::raw('(AVG(c.value * (r.sum_of_values / NULLIF(r.count_of_units, 0))) - AVG(c.value) * (r.sum_of_values / NULLIF(r.count_of_units, 0))) as cov_yx_approx'),
            )
            ->groupBy('a.variant_key', 'r.sum_of_values', 'r.count_of_units')
            ->get();

        $stats = [];

        foreach ($rows as $row) {
            $stats[(string) $row->variant_key] = [
                'mean'     => (float) ($row->cov_mean ?? 0.0),
                'variance' => (float) ($row->cov_variance ?? 0.0),
                'cov_yx'   => (float) ($row->cov_yx_approx ?? 0.0),
                'n'        => (int) $row->n,
            ];
        }

        return $stats;
    }

    public function globalMean(string $experimentKey, string $metricKey): float
    {
        $mean = DB::table('ab_testing_covariates')
            ->where('experiment_key', $experimentKey)
            ->where('metric_key', $metricKey)
            ->avg('value');

        return (float) ($mean ?? 0.0);
    }
}
