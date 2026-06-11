<?php

declare(strict_types=1);

namespace ABTests\Infrastructure\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Database model for the ab_testing_assignments table. One row per
 * (experiment_key, unit_type, unit_key) triple. Insertion is idempotent:
 * the repository uses INSERT IGNORE / ON CONFLICT DO NOTHING so the first
 * write always wins without raising a uniqueness exception.
 *
 * @property string      $experiment_key
 * @property string      $unit_type
 * @property string      $unit_key
 * @property string      $variant_key
 * @property string|null $layer
 * @property Carbon      $assigned_at
 */
final class AssignmentModel extends Model
{
    protected $table = 'ab_testing_assignments';

    /** The composite primary key makes auto-increment unnecessary. */
    public $incrementing = false;

    /** No created_at / updated_at; assigned_at carries the timestamp. */
    public $timestamps = false;

    protected $fillable = [
        'experiment_key',
        'unit_type',
        'unit_key',
        'variant_key',
        'layer',
        'assigned_at',
    ];

    protected $casts = [
        'assigned_at' => 'datetime',
    ];
}
