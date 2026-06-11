<?php

declare(strict_types=1);

namespace ABTests\Infrastructure\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int         $id
 * @property string      $experiment_key
 * @property string      $variant_key
 * @property string      $metric_key
 * @property int         $count_of_units
 * @property int         $exposures
 * @property float       $sum_of_values
 * @property float       $sum_of_squared_values
 * @property int         $conversions
 * @property Carbon|null $updated_through_at
 * @property Carbon|null $updated_at
 */
final class RollupModel extends Model
{
    protected $table = 'ab_testing_rollups';

    public $timestamps = false;

    protected $fillable = [
        'experiment_key',
        'variant_key',
        'metric_key',
        'count_of_units',
        'exposures',
        'sum_of_values',
        'sum_of_squared_values',
        'conversions',
        'updated_through_at',
        'updated_at',
    ];

    protected $casts = [
        'updated_through_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}
