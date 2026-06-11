<?php

declare(strict_types=1);

namespace ABTests\Infrastructure\Database\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Database model for the ab_testing_experiments table. Represents the
 * mutable operational state of an experiment (status, traffic, kill switch).
 * Structural definition (variants, allocation) lives in code, not here.
 *
 * @property string      $key
 * @property int         $version
 * @property string|null $layer
 * @property string      $status
 * @property int         $traffic_percentage
 * @property bool        $is_killed
 * @property string|null $killed_at
 * @property string|null $started_at
 * @property string|null $stopped_at
 */
final class ExperimentModel extends Model
{
    protected $table = 'ab_testing_experiments';

    protected $fillable = [
        'key',
        'version',
        'layer',
        'status',
        'traffic_percentage',
        'is_killed',
        'killed_at',
        'started_at',
        'stopped_at',
    ];

    protected $casts = [
        'is_killed' => 'boolean',
        'traffic_percentage' => 'integer',
        'version' => 'integer',
        'killed_at' => 'datetime',
        'started_at' => 'datetime',
        'stopped_at' => 'datetime',
    ];
}
