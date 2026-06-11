<?php

declare(strict_types=1);

namespace ABTests\Infrastructure\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Operational state for a feature flag. Schema present in v1; dashboard UI
 * surface is planned for v2.
 *
 * @property int         $id
 * @property string      $key
 * @property bool        $is_enabled
 * @property int         $rollout_percentage
 * @property Carbon|null $killed_at
 */
final class FeatureFlagStateModel extends Model
{
    protected $table = 'ab_testing_feature_flag_states';

    protected $fillable = [
        'key',
        'is_enabled',
        'rollout_percentage',
        'killed_at',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'killed_at' => 'datetime',
    ];
}
