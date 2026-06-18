<?php

declare(strict_types=1);

namespace ABTests\Infrastructure\Database\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Database model for the ab_testing_experiments table. Represents the
 * mutable operational state of an experiment (status, traffic, kill switch).
 * Structural definition (variants, allocation) lives in code, not here.
 *
 * @property string $key
 * @property string|null $name
 * @property int $version
 * @property string|null $layer
 * @property string $status
 * @property list<string>|null $allowed_environments
 * @property int $traffic_percentage
 * @property bool $is_killed
 * @property Carbon|null $killed_at
 * @property Carbon|null $started_at
 * @property Carbon|null $stopped_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Collection<int,VariantModel> $variants
 */
final class ExperimentModel extends Model
{
    protected $table = 'ab_testing_experiments';

    protected $fillable = [
        'key',
        'name',
        'version',
        'layer',
        'status',
        'allowed_environments',
        'traffic_percentage',
        'is_killed',
        'killed_at',
        'started_at',
        'stopped_at',
        'target_sample_size',
    ];

    protected $casts = [
        'allowed_environments' => 'array',
        'is_killed' => 'boolean',
        'killed_at' => 'datetime',
        'started_at' => 'datetime',
        'stopped_at' => 'datetime',
    ];

    /** @return HasMany<VariantModel, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(VariantModel::class, 'experiment_id');
    }
}
