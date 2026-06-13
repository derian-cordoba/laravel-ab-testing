<?php

declare(strict_types=1);

namespace ABTests\Infrastructure\Database\Models;

use ABTests\Enums\ConditionsLogic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Operational state for a feature flag.
 *
 * @property int                                                              $id
 * @property string                                                           $key
 * @property bool                                                             $is_enabled
 * @property int                                                              $rollout_percentage
 * @property list<array{attribute:string,operator:string,expected:mixed}>|null $conditions
 * @property ConditionsLogic                                                  $conditions_logic
 * @property Carbon|null                                                      $killed_at
 * @property Carbon|null                                                      $last_evaluated_at
 */
final class FeatureFlagStateModel extends Model
{
    protected $table = 'ab_testing_feature_flag_states';

    protected $fillable = [
        'key',
        'is_enabled',
        'rollout_percentage',
        'conditions',
        'conditions_logic',
        'killed_at',
        'last_evaluated_at',
    ];

    protected $casts = [
        'is_enabled'        => 'boolean',
        'conditions'        => 'array',
        'conditions_logic'  => ConditionsLogic::class,
        'killed_at'         => 'datetime',
        'last_evaluated_at' => 'datetime',
    ];
}
