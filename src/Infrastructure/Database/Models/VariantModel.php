<?php

declare(strict_types=1);

namespace ABTests\Infrastructure\Database\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Snapshot of one variant arm for a specific experiment version. Written when
 * an experiment is synced so the dashboard always has variant metadata even
 * when the code-defined class is unavailable.
 *
 * @property int    $id
 * @property int    $experiment_id
 * @property string $key
 * @property int    $weight
 * @property bool   $is_control
 */
final class VariantModel extends Model
{
    protected $table = 'ab_testing_variants';

    protected $fillable = [
        'experiment_id',
        'key',
        'weight',
        'is_control',
    ];

    protected $casts = [
        'weight'     => 'integer',
        'is_control' => 'boolean',
    ];

    /** @return BelongsTo<ExperimentModel, $this> */
    public function experiment(): BelongsTo
    {
        return $this->belongsTo(ExperimentModel::class, 'experiment_id');
    }
}
