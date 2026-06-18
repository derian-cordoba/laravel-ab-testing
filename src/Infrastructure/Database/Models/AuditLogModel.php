<?php

declare(strict_types=1);

namespace ABTests\Infrastructure\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $actor_identifier
 * @property string|null $actor_type
 * @property string $action
 * @property string|null $experiment_key
 * @property array|null $before_state
 * @property array|null $after_state
 * @property Carbon $occurred_at
 */
final class AuditLogModel extends Model
{
    protected $table = 'ab_testing_audit_log';

    public $timestamps = false;

    protected $fillable = [
        'actor_identifier',
        'actor_type',
        'action',
        'experiment_key',
        'before_state',
        'after_state',
        'occurred_at',
    ];

    protected $casts = [
        'before_state' => 'array',
        'after_state' => 'array',
        'occurred_at' => 'datetime',
    ];
}
