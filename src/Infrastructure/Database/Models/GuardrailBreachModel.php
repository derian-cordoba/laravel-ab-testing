<?php

declare(strict_types=1);

namespace ABTests\Infrastructure\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $experiment_key
 * @property string $metric_key
 * @property string $variant_key
 * @property float $observed_value
 * @property float $threshold_value
 * @property Carbon $breached_at
 * @property bool $is_acknowledged
 * @property Carbon|null $acknowledged_at
 */
final class GuardrailBreachModel extends Model
{
    protected $table = 'ab_testing_guardrail_breaches';

    public $timestamps = false;

    protected $fillable = [
        'experiment_key',
        'metric_key',
        'variant_key',
        'observed_value',
        'threshold_value',
        'breached_at',
        'is_acknowledged',
        'acknowledged_at',
    ];

    protected $casts = [
        'is_acknowledged' => 'boolean',
        'breached_at' => 'datetime',
        'acknowledged_at' => 'datetime',
    ];
}
