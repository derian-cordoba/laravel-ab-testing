<?php

declare(strict_types=1);

namespace ABTests\Infrastructure\Database\Models;

use ABTests\Enums\EventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $experiment_key
 * @property string $unit_type
 * @property string $unit_key
 * @property string $variant_key
 * @property EventType $type
 * @property string|null $metric_key
 * @property float|null $value
 * @property array|null $properties
 * @property string $idempotency_key
 * @property Carbon $occurred_at
 */
final class EventModel extends Model
{
    protected $table = 'ab_testing_events';

    public $timestamps = false;

    protected $fillable = [
        'experiment_key',
        'unit_type',
        'unit_key',
        'variant_key',
        'type',
        'metric_key',
        'value',
        'properties',
        'idempotency_key',
        'occurred_at',
    ];

    protected $casts = [
        'type' => EventType::class,
        'value' => 'float',
        'properties' => 'array',
        'occurred_at' => 'datetime',
    ];
}
