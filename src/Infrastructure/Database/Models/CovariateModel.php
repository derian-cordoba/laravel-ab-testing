<?php

declare(strict_types=1);

namespace ABTests\Infrastructure\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Pre-experiment covariate observation for CUPED variance reduction.
 *
 * @property int    $id
 * @property string $experiment_key
 * @property string $metric_key
 * @property string $unit_type
 * @property string $unit_key
 * @property float  $value
 * @property Carbon $recorded_at
 */
final class CovariateModel extends Model
{
    protected $table = 'ab_testing_covariates';

    public $timestamps = false;

    protected $fillable = [
        'experiment_key',
        'metric_key',
        'unit_type',
        'unit_key',
        'value',
        'recorded_at',
    ];

    protected $casts = [
        'value'       => 'float',
        'recorded_at' => 'datetime',
    ];
}
