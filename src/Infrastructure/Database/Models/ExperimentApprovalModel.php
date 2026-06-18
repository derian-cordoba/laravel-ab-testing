<?php

declare(strict_types=1);

namespace ABTests\Infrastructure\Database\Models;

use ABTests\Enums\ApprovalStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Persists the approval lifecycle for one experiment. Only the latest row per
 * experiment_key is authoritative — older rows are kept for the audit trail.
 *
 * @property int $id
 * @property string $experiment_key
 * @property string $status
 * @property string $requested_by
 * @property string $requested_by_type
 * @property string|null $reviewed_by
 * @property string|null $reviewed_by_type
 * @property string|null $notes
 * @property Carbon|null $reviewed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class ExperimentApprovalModel extends Model
{
    protected $table = 'ab_testing_experiment_approvals';

    protected $fillable = [
        'experiment_key',
        'status',
        'requested_by',
        'requested_by_type',
        'reviewed_by',
        'reviewed_by_type',
        'notes',
        'reviewed_at',
    ];

    protected $casts = [
        'status' => ApprovalStatus::class,
        'reviewed_at' => 'datetime',
    ];
}
